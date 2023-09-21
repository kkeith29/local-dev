<?php declare(strict_types=1);

namespace App\Services\UseFormatter;

use Exception;

/**
 * Class Item
 *
 * @package App\Services\UseFormatter
 */
class Item
{
    protected static int $last_id = 0;

    public readonly int $id;

    public ?ItemContainer $container = null;

    protected ?ItemContainer $children = null;

    protected ?int $depth = null;

    protected ?int $count_to_last = null;

    public bool $grouped = false;

    /**
     * Item constructor
     *
     * Set unique id for item
     */
    public function __construct(public string $name, public bool $use = false, public ?string $alias = null)
    {
        $this->id = self::$last_id++;
    }

    /**
     * Get name of item with optional alias (if defined)
     */
    public function getName(bool $with_alias = true): string
    {
        $name = $this->name;
        if ($with_alias && $this->alias !== null) {
            $name .= " as {$this->alias}";
        }
        return $name;
    }

    /**
     * Get namespace of item
     *
     * Loops through parents of item to build namespace string. If $until item is provided, the namespace generation
     * will stop when that $until item is hit in the parent list.
     *
     * @throws \Exception
     */
    public function getNamespace(?Item $until = null): string
    {
        if (($parent = $this->getContainer()->parent) === null || $parent === $until) {
            return '';
        }
        if (($namespace = $parent->getNamespace($until)) !== '') {
            $namespace .= '\\';
        }
        return $namespace . $parent->name;
    }

    /**
     * Get fully qualified name (FQN) using namespace and name
     *
     * @throws \Exception
     */
    public function getFullyQualifiedName(bool $with_alias = true, ?Item $until = null): string
    {
        if (($namespace = $this->getNamespace($until)) !== '') {
            $namespace .= '\\';
        }
        return $namespace . $this->getName($with_alias);
    }

    /**
     * Get container which this item resides in
     *
     * @throws \Exception
     */
    public function getContainer(): ItemContainer
    {
        if ($this->container === null) {
            throw new Exception('No parent defined on Item');
        }
        return $this->container;
    }

    /**
     * Determines if item has any children
     */
    public function hasChildren(): bool
    {
        return isset($this->children) && !$this->children->isEmpty();
    }

    /**
     * Get children item container (or create if it doesn't exist)
     */
    public function getChildren(): ItemContainer
    {
        $this->children ??= new ItemContainer($this);
        return $this->children;
    }

    /**
     * Get depth of item from the root container
     *
     * @throws \Exception
     */
    public function getDepth(): int
    {
        if ($this->depth === null) {
            $this->depth = $this->getContainer()->getDepth() + 1;
        }
        return $this->depth;
    }

    /**
     * Determines if item is the last in its branch (has no children)
     */
    public function isLast(): bool
    {
        return !$this->hasChildren();
    }

    /**
     * Get and cache the number of levels this item is from the end of it's branch
     */
    public function getCountToLast(): int
    {
        if ($this->count_to_last === null) {
            $this->count_to_last = $this->hasChildren() ? $this->getChildren()->getCountToLast() : 0;
        }
        return $this->count_to_last;
    }

    /**
     * Get parent at specified depth
     *
     * Traverses up the tree to find parent at $depth and returns it. If no parent is found, then null is returned.
     *
     * @throws \Exception
     */
    public function getParent(int $depth): ?Item
    {
        $item = $this;
        while ($depth > 0 && $item !== null) {
            $item = $item->getContainer()->parent;
            $depth--;
        }
        return $item;
    }
}
