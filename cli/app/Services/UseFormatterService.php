<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\UseFormatter\Exceptions\{InvalidStatementException, NoStatementsFoundException};
use ArrayIterator;

use function Core\Functions\env;

/**
 * Class UseFormatterService
 *
 * @package App\Services
 */
class UseFormatterService
{
    const STATEMENT_TYPE_CLASS = 1;
    const STATEMENT_TYPE_CONST = 2;
    const STATEMENT_TYPE_FUNCTION = 3;

    /**
     * Add statement parsed from input to global list
     *
     * @param array $statements
     * @param string $statement
     */
    protected function addStatement(array &$statements, string $statement): void
    {
        $parts = explode('\\', $statement);
        $count = count($parts);
        for ($i = 0; $i < $count; $i++) {
            $part = $parts[$i];
            if ($i === $count - 1) {
                $pieces = preg_split('@\s+as\s+@', $part, 2);
                $alias = null;
                if (count($pieces) === 2) {
                    [$part, $alias] = $pieces;
                }
                $statements[$part]['use'] = true;
                $statements[$part]['alias'] = $alias;
                break;
            }
            $statements[$part] ??= ['use' => false];
            $statements[$part]['items'] ??= [];
            $statements =& $statements[$part]['items'];
        }
    }

    /**
     * Get depth of config
     *
     * @param array $config
     * @return int
     */
    protected function getDepth(array $config): int
    {
        $depth = 0;
        if (isset($config['items'])) {
            $max_depth = 0;
            foreach ($config['items'] as $item_config) {
                $new_depth = $this->getDepth($item_config);
                if ($new_depth <= $max_depth) {
                    continue;
                }
                $max_depth = $new_depth;
            }
            $depth = $max_depth + 1;
        }
        return $depth;
    }

    /**
     * Determine depth of nested statements at each level of list
     *
     * @param array $statements
     * @param array|null $parent
     * @return array
     */
    protected function assignDepths(array $statements, array &$parent = null): array
    {
        $new_statements = [];
        foreach ($statements as $name => $config) {
            $new_config = $config;
            $new_config['name'] = $name;
            if ($parent !== null) {
                $new_config['parent'] =& $parent;
            }
            $new_config['depth'] = $this->getDepth($config);
            if (isset($config['items'])) {
                $new_config['items'] = $this->assignDepths($config['items'], $new_config);
            }
            $new_statements[$name] = $new_config;
            unset($new_config);
        }
        return $new_statements;
    }

    /**
     * Determines if config has any siblings in list
     *
     * @param array $config
     * @param string $skip_name
     * @return bool
     */
    protected function hasSibling(array $config, string $skip_name): bool
    {
        $has_sibling = false;
        if (isset($config['items'])) {
            foreach ($config['items'] as $item) {
                if ($skip_name === $item['name'] || $item['depth'] > 1) {
                    continue;
                }
                if ($item['depth'] === 1 && count($item['items']) >= 2) {
                    continue;
                }
                $has_sibling = true;
                break;
            }
        }
        return $has_sibling;
    }

    /**
     * Build statements from nested list
     *
     * Goes to the end of each branch in the nested list and then determines the grouping and namespace of each statement.
     *
     * @param array $list
     * @param array $parents
     * @return array
     */
    protected function buildStatements(array $list, array $parents = []): array
    {
        $statements = [];
        foreach ($list as $config) {
            if ($config['depth'] > 0) {
                $statements = array_merge($statements, $this->buildStatements($config['items'], [...$parents, $config['name']]));
                continue;
            }
            $pop = 0;
            if (isset($config['parent'])) {
                $parent = $config['parent'];
                if (
                    ($parent['depth'] === 2 && !$this->hasSibling($parent, $config['name'])) ||
                    (
                        $parent['depth'] === 1 &&
                        count($parent['items']) < 2 &&
                        isset($parent['parent']) &&
                        $this->hasSibling($parent['parent'], $parent['name'])
                    )
                ) {
                    $pop = 1;
                }
            }
            $statement_parents = $parents;
            $name = $config['name'] . (isset($config['alias']) ? " as {$config['alias']}" : '');
            if ($pop > 0) {
                while ($pop > 0) {
                    $pop--;
                    $parent = array_pop($statement_parents);
                    $name = "{$parent}\\{$name}";
                }
            }
            $namespace = count($statement_parents) === 0 ? '--ROOT--' : implode('\\', $statement_parents);
            $statements[] = [$namespace, $name];
        }
        return $statements;
    }

    /**
     * Take structured statement list, sort, and render use statements
     *
     * @param array $list
     * @param int $type
     * @param int|null $max_line_length
     * @return string
     */
    protected function renderStatements(array $list, int $type, ?int $max_line_length): string
    {
        $statements = [];
        // group all similar classes
        foreach ($list as [$namespace, $class]) {
            $statements[$namespace] ??= [];
            $statements[$namespace][] = $class;
        }
        // handle root level classes
        if (isset($statements['--ROOT--'])) {
            foreach ($statements['--ROOT--'] as $class) {
                $statements[$class] = true;
            }
            unset($statements['--ROOT--']);
        }
        // handle any single class namespaces, necessary to get sorting order proper since we sort by keys
        foreach ($statements as $namespace => $names) {
            if (!is_array($names) || count($names) > 1) {
                continue;
            }
            $statements["{$namespace}\\{$names[0]}"] = true;
            unset($statements[$namespace]);
        }
        ksort($statements);
        // group single classes into non bracketed use statements
        $items = [];
        $singles = [];
        $iterator = new ArrayIterator($statements);
        while ($iterator->valid()) {
            $namespace = $iterator->key();
            $names = $iterator->current();
            $iterator->next();
            $has_classes = is_array($names);
            if (!$has_classes) {
                $singles[] = $namespace;
            }
            if (($has_classes || !$iterator->valid()) && count($singles) > 0) {
                $items[] = ['names' => $singles];
                $singles = [];
            }
            if ($has_classes) {
                $items[] = ['namespace' => $namespace, 'names' => $names];
            }
        }
        $lines = [];
        $max_line_length ??= (int) env('MAX_LINE_LENGTH', 120);
        $type = match($type) {
            self::STATEMENT_TYPE_CONST => 'const ',
            self::STATEMENT_TYPE_FUNCTION => 'function ',
            default => ''
        };
        foreach ($items as $item) {
            // if group doesn't have a namespace, then we make a comma separated indented list
            if (!isset($item['namespace'])) {
                $line = "use {$type}";
                $offset = strlen($line);
                $allowed_length = $max_line_length - $offset;
                $length = 0;
                $iterator = new ArrayIterator($item['names']);
                while ($iterator->valid()) {
                    $data = $iterator->current();
                    $iterator->next();
                    if ($iterator->valid()) {
                        $data .= ', ';
                    }
                    $data_length = strlen($data);
                    $fits = $length + $data_length <= $allowed_length;
                    if (!$fits) {
                        $line .= PHP_EOL . str_repeat(' ', $offset);
                        $length = 0;
                    }
                    $line .= $data;
                    $length += $data_length;
                }
                $lines[] = $line . ';';
                continue;
            }
            $names = $item['names'];
            sort($names);
            $prefix = "use {$type}{$item['namespace']}\\{";
            $length = strlen($prefix);
            $length += array_reduce($names, fn(int $length, string $name): int => $length + strlen($name), 0);
            $length += (count($names) - 1) * 2; // separators between names
            $length += 2; // end };
            if ($length > $max_line_length) {
                $lines[] = $prefix . PHP_EOL . '    ' . implode(',' . PHP_EOL . '    ', $names) . PHP_EOL . '};';
                continue;
            }
            $lines[] = $prefix . implode(', ', $names) . '};';
        }
        return implode(PHP_EOL, $lines);
    }

    /**
     * Expand statement into multiple statements if comma is found
     *
     * @param array $statements
     * @param string $statement
     * @param string $prefix
     * @return void
     */
    protected function expandStatements(array &$statements, string $statement, string $prefix = ''): void
    {
        foreach (explode(',', $statement) as $item) {
            $item = trim($item);
            $this->addStatement($statements, $prefix . $item);
        }
    }

    /**
     * Parse and format PHP code which should contain use statements
     *
     * @param string $content
     * @param int|null $max_line_length
     * @return string
     * @throws InvalidStatementException
     * @throws NoStatementsFoundException
     */
    public function format(string $content, ?int $max_line_length = null): string
    {
        $content = trim($content);
        if (strlen($content) === 0) {
            throw new NoStatementsFoundException('No content provided');
        }
        if (!str_contains($content, ';')) {
            throw new NoStatementsFoundException('No statements found which end with semicolon');
        }
        $statements = [
            self::STATEMENT_TYPE_CLASS => [],
            self::STATEMENT_TYPE_CONST => [],
            self::STATEMENT_TYPE_FUNCTION => []
        ];
        while (($pos = strpos($content, ';')) !== false) {
            $statement = trim(substr($content, 0, $pos));
            if (preg_match('#^use\s+(const|function)?(\s+)?#', $statement, $match) !== 1) {
                throw new InvalidStatementException('Use prefix not found in statement');
            }
            $type = match($match[1] ?? null) {
                'const' => self::STATEMENT_TYPE_CONST,
                'function' => self::STATEMENT_TYPE_FUNCTION,
                default => self::STATEMENT_TYPE_CLASS
            };
            $statement = str_replace(["\r\n", "\r", "\n", "\t"], '', substr($statement, strlen($match[0])));
            if (($bpos = strpos($statement, '{')) !== false) {
                if (!str_ends_with($statement, '}')) {
                    throw new InvalidStatementException('Invalid bracket usage');
                }
                $prefix = trim(substr($statement, 0, $bpos - 1)) . '\\';
                $statement = substr($statement, $bpos + 1, (strlen($statement) - $bpos - 2));
                $this->expandStatements($statements[$type], $statement, $prefix);
            } else {
                $this->expandStatements($statements[$type], $statement);
            }
            $content = substr($content, $pos + 1);
        }
        $groups = [];
        foreach ($statements as $type => $data) {
            if (count($data) === 0) {
                continue;
            }
            $data = $this->assignDepths($data);
            $data = $this->buildStatements($data);
            $groups[] = $this->renderStatements($data, $type, $max_line_length);
        }
        return implode(str_repeat(PHP_EOL, 2), $groups) . PHP_EOL;
    }
}
