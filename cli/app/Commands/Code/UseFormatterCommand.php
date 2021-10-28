<?php

declare(strict_types=1);

namespace app\commands\code;

use Symfony\Component\Console\{Command\Command, Input\InputInterface};
use Symfony\Component\Console\Output\{ConsoleOutputInterface, OutputInterface};
use Throwable;

use function Core\Functions\env;

/**
 * Class UseFormatterCommand
 *
 * @package App\Commands
 */
class UseFormatterCommand extends Command
{
    /**
     * @var string Name of command
     */
    protected static $defaultName = 'code:use-formatter';

    /**
     * Add class parsed from input to global list
     *
     * @param array $classes
     * @param string $class
     */
    protected function addClass(array &$classes, string $class): void
    {
        $parts = explode('\\', $class);
        $count = count($parts);
        for ($i = 0; $i < $count; $i++) {
            $part = $parts[$i];
            if ($i === $count - 1) {
                $pieces = preg_split('@\s+as\s+@', $part, 2);
                $alias = null;
                if (count($pieces) === 2) {
                    [$part, $alias] = $pieces;
                }
                $classes[$part]['use'] = true;
                $classes[$part]['alias'] = $alias;
                break;
            }
            $classes[$part] ??= ['use' => false];
            $classes[$part]['items'] ??= [];
            $classes =& $classes[$part]['items'];
        }
    }

    /**
     * Get depth of class config
     *
     * @param array $class
     * @return int
     */
    protected function getDepth(array $class): int
    {
        $depth = 0;
        if (isset($class['items'])) {
            $max_depth = 0;
            foreach ($class['items'] as $config) {
                $new_depth = $this->getDepth($config);
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
     * Determine depth of nested classes at each level of class list
     *
     * @param array $classes
     * @param array|null $parent
     * @return array
     */
    protected function assignDepths(array $classes, array &$parent = null): array
    {
        $new_classes = [];
        foreach ($classes as $name => $config) {
            $new_config = $config;
            $new_config['name'] = $name;
            if ($parent !== null) {
                $new_config['parent'] =& $parent;
            }
            $new_config['depth'] = $this->getDepth($config);
            if (isset($config['items'])) {
                $new_config['items'] = $this->assignDepths($config['items'], $new_config);
            }
            $new_classes[$name] = $new_config;
            unset($new_config);
        }
        return $new_classes;
    }

    /**
     * Determines if class config has any siblings in list
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
     * Build statements from nested class list
     *
     * Goes to the end of each branch in the nested list and then determines the grouping and namespace of each class.
     *
     * @param array $classes
     * @param array $parents
     * @return array
     */
    protected function buildStatements(array $classes, array $parents = []): array
    {
        $statements = [];
        foreach ($classes as $config) {
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
     * Take structured class list, sort, and render use statements
     *
     * @param array $list
     * @return string
     */
    protected function renderClasses(array $list): string
    {
        $classes = [];
        // group all similar classes
        foreach ($list as [$namespace, $class]) {
            $classes[$namespace] ??= [];
            $classes[$namespace][] = $class;
        }
        // handle root level classes
        if (isset($classes['--ROOT--'])) {
            foreach ($classes['--ROOT--'] as $class) {
                $classes[$class] = true;
            }
            unset($classes['--ROOT--']);
        }
        // handle any single class namespaces, necessary to get sorting order proper since we sort by keys
        foreach ($classes as $namespace => $names) {
            if (!is_array($names) || count($names) > 1) {
                continue;
            }
            $classes["{$namespace}\\{$names[0]}"] = true;
            unset($classes[$namespace]);
        }
        ksort($classes);
        $max_line_length = env('MAX_LINE_LENGTH', 120);
        $lines = [];
        foreach ($classes as $namespace => $names) {
            if ($names === true) {
                $lines[] = "use {$namespace};";
                continue;
            }
            sort($names);
            $prefix = "use {$namespace}\\{";
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
        return implode(\PHP_EOL, $lines);
    }

    /**
     * Execute command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws \Exception
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $data = stream_get_contents(STDIN);
        try {
            $classes = [];
            $content = trim($data);
            while (($pos = strpos($content, ';')) !== false) {
                $statement = trim(substr($content, 0, $pos));
                if (!str_starts_with($statement, 'use ')) {
                    throw new \Exception('Use statement not found');
                }
                $statement = str_replace(["\r\n", "\r", "\n", "\t"], '', substr($statement, 4));
                if (($bpos = strpos($statement, '{')) !== false) {
                    if (!str_ends_with($statement, '}')) {
                        throw new \Exception('Invalid bracket usage');
                    }
                    $base_class = trim(substr($statement, 0, $bpos - 1));
                    foreach (explode(',', substr($statement, $bpos + 1, (strlen($statement) - $bpos - 2))) as $class) {
                        $class = trim($class);
                        $this->addClass($classes, "{$base_class}\\{$class}");
                    }
                } else {
                    $this->addClass($classes, $statement);
                }
                $content = substr($content, $pos + 1);
            }
            if (count($classes) === 0) {
                throw new \Exception('No classes found');
            }
            $classes = $this->assignDepths($classes);
            $classes = $this->buildStatements($classes);
            $output->write($this->renderClasses($classes), true);
            return Command::SUCCESS;
        } catch (Throwable $e) {
            if ($output instanceof ConsoleOutputInterface) {
                $output->getErrorOutput()->writeln(">>> {$e->getMessage()}");
            }
            $output->write($data);
            return Command::FAILURE;
        }
    }
}
