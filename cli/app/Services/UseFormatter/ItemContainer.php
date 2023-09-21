<?php declare(strict_types=1);

namespace App\Services\UseFormatter;

use ArrayIterator, Countable, Exception, Generator, IteratorAggregate, Traversable;

use function array_filter, array_shift, count, uasort;

/**
 * Class ItemContainer
 *
 * @package App\Services\UseFormatter
 * @template-implements IteratorAggregate<string, Item>
 */
class ItemContainer implements IteratorAggregate, Countable
{
    protected static int $last_id = 0;

    public readonly int $id;

    /**
     * @var array<string, Item>
     */
    protected array $items = [];

    protected ?int $depth = null;

    protected ?int $count_to_last = null;

    /**
     * ItemContainer constructor
     *
     * Set unique id for container
     */
    public function __construct(public readonly Item|null $parent = null)
    {
        $this->id = self::$last_id++;
    }

    /**
     * Determine if container has item by name
     */
    public function has(string $name): bool
    {
        return isset($this->items[$name]);
    }

    /**
     * Add item to container and assign container to item
     */
    public function add(Item $item): void
    {
        $item->container = $this;
        $this->items[$item->name] = $item;
    }

    /**
     * Find item by name or return null if not found
     */
    public function find(string $name): ?Item
    {
        return $this->items[$name] ?? null;
    }

    /**
     * Find item by name or create new item and return
     */
    public function findOrCreate(string $name): Item
    {
        if (($item = $this->find($name)) === null) {
            $item = new Item($name);
            $this->add($item);
        }
        return $item;
    }

    /**
     * Determines if container has any items
     */
    public function isEmpty(): bool
    {
        return count($this->items) === 0;
    }

    /**
     * Get iterator from array of internal items to allow array like interaction
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    /**
     * Number of items in container
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Get and cache the number of levels this item is from the first level
     */
    public function getDepth(): int
    {
        if ($this->depth === null) {
            $this->depth = $this->parent?->getDepth() ?? 0;
        }
        return $this->depth;
    }

    /**
     * Get and cache the number of levels this item is from the end of it's branch
     */
    public function getCountToLast(): int
    {
        if ($this->count_to_last === null) {
            $max_depth = 0;
            foreach ($this->items as $item) {
                $new_depth = $item->getCountToLast();
                if ($new_depth <= $max_depth) {
                    continue;
                }
                $max_depth = $new_depth;
            }
            $this->count_to_last = $max_depth + 1;
        }
        return $this->count_to_last;
    }

    /**
     * Get all items which are siblings
     *
     * A sibling is determined if the item is marked as 'use' or it's the last item in its branch (no children).
     *
     * @return Item[]
     */
    public function getSiblings(): array
    {
        $siblings = [];
        foreach ($this->items as $sibling) {
            if (!$sibling->use && !$sibling->isLast()) {
                continue;
            }
            $siblings[] = $sibling;
        }
        return $siblings;
    }

    /**
     * Get all renderable items from container and children
     *
     * Some items which are marked as 'use' need to be added separately to the item list to ensure they are rendered.
     *
     * The 'use' property signifies if an item has children but should also be added to the output list. Ex:
     *
     * use A\B\C;
     * use A\B\C\D;
     *
     * Item 'C' should be included in the output, but it also has children meaning it will not be caught by the 'is
     * last in branch' check which works for most items.
     *
     * Items are keyed by their unique id.
     *
     * @return Item[]
     * @throws \Exception
     */
    public function getRenderableItems(): array
    {
        $items = [];
        foreach ($this->items as $item) {
            if (($item->use || $item->isLast()) && !$item->grouped) {
                $items[$item->id] = $item;
            }
            if ($item->getCountToLast() > 0 && $item->hasChildren()) {
                $items += $item->getChildren()->getRenderableItems();
            }
        }
        return $items;
    }

    /**
     * Create group of items based on number of siblings in same container
     *
     * If a container has at least $min_count items which are considered siblings, then we create a specific grouping
     * and remove the items from the overall list. This produces a cleaner output and more of a style choice than
     * necessity to follow guidelines.
     *
     * Without this grouping:
     * use A\B\C;   --->    use A\{B\C, B\D};
     * use A\B\D;
     *
     * With this grouping:
     * use A\B\C;   --->    use A\B\{C, D};
     * use A\B\D;
     *
     * @param Item[] $items
     * @return \Generator<int, array{namespace: string, names: string[], priority: int}>
     * @throws \Exception
     */
    protected function groupItemsBySiblingCount(array &$items, int $min_count): Generator
    {
        foreach ($items as $item) {
            if ($item->grouped) {
                continue;
            }
            $container = $item->getContainer();
            // skip root level items as they are sorted later on by a separate process
            if ($container->parent === null) {
                continue;
            }
            if (count($siblings = $container->getSiblings()) < $min_count) {
                continue;
            }
            $names = [];
            foreach ($siblings as $sibling) {
                $names[] = $sibling->getName();
                $sibling->grouped = true;
                unset($items[$sibling->id]);
            }
            yield ['namespace' => $item->getNamespace(), 'names' => $names, 'priority' => 2];
        }
    }

    /**
     * Group items by finding all available within a depth range
     *
     * Items are found by first going to the parent $depth levels from the current items depth.
     *
     *   Current item   Parent item ($depth = 2)
     *       ↓               ↓
     * A\B\C\D             A\B\C\D
     *
     * If no parent is found because the current item is shallow, then we just pass through the fully qualified name of
     * item.
     *
     * Otherwise, we search downwards from parent (P) through all items which match up until the same depth as our current item (C).
     *   P   C
     *   ↓   ↓
     * A\B\C\D        should match    C\D, G, J\K, but not N\O\P as it's not within our depth rules (it goes past the
     * E\F\G                          depth of the current item)
     * H\I\J\K
     * L\M\N\O\P
     *
     * @param Item[] $items
     * @return \Generator<int, array{fqn?: string, namespace?: string, names?: string[], priority: int}>
     * @throws \Exception
     */
    protected function groupItemsByDepthFromBranchEnd(array &$items, int $depth): Generator
    {
        uasort($items, fn(Item $a, Item $b): int => $b->getDepth() <=> $a->getDepth());
        foreach ($items as $item) {
            if ($item->grouped) {
                continue;
            }
            if (($parent = $item->getParent($depth)) === null) {
                yield ['fqn' => $item->getFullyQualifiedName(), 'priority' => 1];
                $item->grouped = true;
                unset($items[$item->id]);
                continue;
            }
            $group_items = $parent->getChildren()->getRenderableItems();
            $item_depth = $item->getDepth();
            $group_items = array_filter($group_items, fn(Item $item): bool => $item->getDepth() <= $item_depth);
            if (count($group_items) === 1) {
                /** @var Item $first_item */
                $first_item = array_shift($group_items);
                yield ['fqn' => $first_item->getFullyQualifiedName(), 'priority' => 1];
                $first_item->grouped = true;
                unset($items[$first_item->id]);
                continue;
            }
            $names = [];
            foreach ($group_items as $group_item) {
                $names[] = $group_item->getFullyQualifiedName(true, $parent);
                $group_item->grouped = true;
                unset($items[$group_item->id]);
            }
            yield ['namespace' => $parent->getFullyQualifiedName(false), 'names' => $names, 'priority' => 2];
        }
    }

    /**
     * Group and yield renderable items by sibling count then by depth
     *
     * @return \Generator<int, array{fqn?: string, namespace?: string, names?: string[], priority: int}>
     * @throws \Exception
     */
    public function getGroupedItems(int $min_sibling_group_count, int $group_depth_count): Generator
    {
        $items = $this->getRenderableItems();
        yield from $this->groupItemsBySiblingCount($items, $min_sibling_group_count);
        if (count($items) > 0) {
            yield from $this->groupItemsByDepthFromBranchEnd($items, $group_depth_count);
        }
        if (count($items) !== 0) {
            throw new Exception('Unable to group all items');
        }
    }
}
