<?php declare(strict_types=1);

namespace App\Services;

use App\Services\UseFormatter\Exceptions\{InvalidStatementException, NoStatementsFoundException};
use App\Services\UseFormatter\ItemContainer;
use ArrayIterator;

use function array_reduce, count, explode, implode, iterator_to_array, preg_match, preg_split, sort, str_contains,
             str_ends_with, str_repeat, str_replace, strcmp, strlen, strpos, substr, substr_count, trim, usort;

use const PHP_EOL, PHP_INT_MAX;

/**
 * Class UseFormatterService
 *
 * @package App\Services
 */
class UseFormatterService
{
    protected const STATEMENT_TYPE_CLASS = 1;
    protected const STATEMENT_TYPE_CONST = 2;
    protected const STATEMENT_TYPE_FUNCTION = 3;

    /**
     * Add statement parsed from input to an item container
     */
    protected function addStatement(ItemContainer $container, string $statement): void
    {
        $parts = explode('\\', $statement);
        $count = count($parts);
        $last = $count - 1;
        for ($i = 0; $i < $count; $i++) {
            $part = $parts[$i];
            if ($i === $last) {
                $pieces = preg_split('@\s+as\s+@', $part, 2);
                $alias = null;
                if (count($pieces) === 2) {
                    [$part, $alias] = $pieces;
                }
                $item = $container->findOrCreate($part);
                $item->use = true;
                $item->alias = $alias;
                break;
            }
            $container = $container->findOrCreate($part)->getChildren();
        }
    }

    /**
     * Gets grouped items from container and sorts them by their name or priority
     *
     * Items are sorted by their fully qualified name (if available) or their namespace. If two items like a FQN and
     * namespace, we then sort by priority to ensure FQN items will show before namespace groups.
     *
     * @return array<int, array{fqn?: string, namespace?: string, names?: string[], priority: int}>
     * @throws \Exception
     */
    protected function getOrderedItems(ItemContainer $container, int $min_sibling_group_count, int $max_group_depth): array
    {
        $items = iterator_to_array($container->getGroupedItems($min_sibling_group_count, $max_group_depth), false);
        // sort statements by fully qualified name or namespace, if FQN and namespace match, sort by individual priority
        // so FQN will show before namespace group
        usort($items, function (array $a, array $b): int {
            $result = strcmp($a['fqn'] ?? $a['namespace'], $b['fqn'] ?? $b['namespace']);
            if ($result === 0) {
                $result = $a['priority'] <=> $b['priority'];
            }
            return $result;
        });
        return $items;
    }

    /**
     * Group single fully qualified name items into non bracketed group for display
     *
     * If FQN items are mixed between bracketed groups, we combine them where possible while keeping alphabetical
     * sorting.
     *
     * @param array $input
     * @return array
     */
    protected function groupFullyQualifiedNameItems(array $input): array
    {
        // group single classes into non bracketed use statements
        $items = [];
        $group = [];
        $iterator = new ArrayIterator($input);
        while ($iterator->valid()) {
            $item = $iterator->current();
            $iterator->next();
            $is_fqn = isset($item['fqn']);
            $is_long = $is_fqn && substr_count($item['fqn'], '\\') > 1;
            if ($is_fqn && !$is_long) {
                $group[] = $item['fqn'];
            }
            if ((!$is_fqn || $is_long || !$iterator->valid()) && count($group) > 0) {
                $items[] = ['group' => $group];
                $group = [];
            }
            if (!$is_fqn || $is_long) {
                $items[] = $item;
            }
        }
        return $items;
    }

    /**
     * Generate non bracketed, comma seperated, fully qualified name list output
     *
     * Adheres to max line lengths and will break list to next line where needed.
     *
     * @param string[] $names
     */
    protected function generateNonBracketedGroupOutput(array $names, int $max_line_length, string $prefix): string
    {
        $line = $prefix;
        $offset = strlen($line);
        $allowed_length = $max_line_length - $offset;
        $length = 0;
        $iterator = new ArrayIterator($names);
        $i = 0;
        while ($iterator->valid()) {
            $i++;
            $data = $iterator->current();
            $iterator->next();
            if ($iterator->valid()) {
                $data .= ', ';
            }
            $data_length = strlen($data);
            // we check to see if it's the first value since we don't want to have a line break in the first
            // position
            $fits = $i === 1 || $length + $data_length <= $allowed_length;
            if (!$fits) {
                $line .= PHP_EOL . str_repeat(' ', $offset);
                $length = 0;
            }
            $line .= $data;
            $length += $data_length;
        }
        return $line . ';';
    }

    /**
     * Generate bracketed namespace output which follows max line length limitations
     *
     * @param string[] $names
     */
    protected function generateBracketedNamespaceOutput(array $names, string $namespace, int $max_line_length, string $prefix): string
    {
        sort($names);
        $prefix = "{$prefix}{$namespace}\\{";
        $length = strlen($prefix);
        $length += array_reduce($names, fn(int $length, string $name): int => $length + strlen($name), 0);
        $length += (count($names) - 1) * 2; // separators between names
        $length += 2; // end };
        if ($length > $max_line_length) {
            return $prefix . PHP_EOL . '    ' . implode(',' . PHP_EOL . '    ', $names) . PHP_EOL . '};';
        }
        return $prefix . implode(', ', $names) . '};';
    }

    /**
     * Generate item specific output
     *
     * Items can be a single fully qualified name, a group of non bracketed and fully qualified names which will
     * be comma separated, or a namespace bracketed group.
     *
     * @param array{fqn?: string, group?: string[], names?: string[], namespace?: string} $item
     */
    protected function generateOutputFromItem(array $item, int $max_line_length, string $prefix): string
    {
        return match (true) {
            isset($item['fqn']) => "{$prefix}{$item['fqn']};",
            isset($item['group']) => $this->generateNonBracketedGroupOutput($item['group'], $max_line_length, $prefix),
            default => $this->generateBracketedNamespaceOutput($item['names'], $item['namespace'], $max_line_length, $prefix)
        };
    }

    /**
     * Create properly formatted output for a container
     *
     * Gets all renderable items ordered alphabetically and sorted according to their type (Single Fully Qualified Name,
     * Comma Separated Group of Fully Qualified Names, and Bracketed Namespace Grouping). Output is generated for each
     * type and concatenated together with a new line.
     */
    protected function renderStatements(
        ItemContainer $container,
        int $type,
        int $max_line_length,
        int $min_sibling_group_count,
        int $max_group_depth
    ): string {
        $items = $this->getOrderedItems($container, $min_sibling_group_count, $max_group_depth);
        $items = $this->groupFullyQualifiedNameItems($items);
        $prefix = match($type) {
            self::STATEMENT_TYPE_CONST => 'use const ',
            self::STATEMENT_TYPE_FUNCTION => 'use function ',
            default => 'use '
        };
        $output = array_map(
            fn(array $item): string => $this->generateOutputFromItem($item, $max_line_length, $prefix),
            $items
        );
        return implode(PHP_EOL, $output);
    }

    /**
     * Expand statement into multiple statements if comma is found
     */
    protected function expandStatements(ItemContainer $container, string $statement, string $prefix = ''): void
    {
        foreach (explode(',', $statement) as $item) {
            $item = trim($item);
            $this->addStatement($container, $prefix . $item);
        }
    }

    /**
     * Parse statement and add to proper container
     *
     * Determines type of use statement, strips all extra whitespace, and expands any bracketed groups found into
     * multiple statements.
     *
     * @param string $statement
     * @param array $containers
     * @return void
     * @throws \App\Services\UseFormatter\Exceptions\InvalidStatementException
     */
    protected function parseStatement(string $statement, array &$containers): void
    {
        if (preg_match('#^use\s+(const|function)?(\s+)?#', $statement, $match) !== 1) {
            throw new InvalidStatementException('Use prefix not found in statement');
        }
        $type = match($match[1] ?? null) {
            'const' => self::STATEMENT_TYPE_CONST,
            'function' => self::STATEMENT_TYPE_FUNCTION,
            default => self::STATEMENT_TYPE_CLASS
        };
        $statement = str_replace(["\r\n", "\r", "\n", "\t"], '', substr($statement, strlen($match[0])));
        $containers[$type] ??= new ItemContainer();
        if (($bpos = strpos($statement, '{')) !== false) {
            if (!str_ends_with($statement, '}')) {
                throw new InvalidStatementException('Invalid bracket usage');
            }
            $prefix = trim(substr($statement, 0, $bpos - 1)) . '\\';
            $statement = substr($statement, $bpos + 1, (strlen($statement) - $bpos - 2));
            $this->expandStatements($containers[$type], $statement, $prefix);
            return;
        }
        $this->expandStatements($containers[$type], $statement);
    }

    /**
     * Parse PHP use statement block and return PER Coding Style 2.0 formatted output
     *
     * The formatting is opinionated, but the specifics follow the standard.
     *
     * @throws InvalidStatementException
     * @throws NoStatementsFoundException
     */
    public function format(
        string $content,
        int $max_line_length = PHP_INT_MAX,
        int $min_sibling_group_count = 2,
        int $max_group_depth = 2
    ): string {
        $content = trim($content);
        if (strlen($content) === 0) {
            throw new NoStatementsFoundException('No content provided');
        }
        if (!str_contains($content, ';')) {
            throw new NoStatementsFoundException('No statements found which end with semicolon');
        }
        $containers = [];
        while (($pos = strpos($content, ';')) !== false) {
            $statement = trim(substr($content, 0, $pos));
            $this->parseStatement($statement, $containers);
            $content = substr($content, $pos + 1);
        }
        $groups = [];
        foreach ([self::STATEMENT_TYPE_CLASS, self::STATEMENT_TYPE_FUNCTION, self::STATEMENT_TYPE_CONST] as $type) {
            if (!isset($containers[$type])) {
                continue;
            }
            $groups[] = $this->renderStatements($containers[$type], $type, $max_line_length, $min_sibling_group_count, $max_group_depth);
        }
        return implode(str_repeat(PHP_EOL, 2), $groups) . PHP_EOL;
    }
}
