<?php declare(strict_types=1);

namespace App\Services;

use App\Services\Workspace\Display;
use App\Services\Workspace\Layer;
use App\Services\Workspace\Layout;
use App\Services\Workspace\Window;
use App\Services\Yabai\Window as YabaiWindow;
use App\Util;
use Exception;
use React\Promise\PromiseInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function React\Async\{coroutine, parallel};
use function React\Promise\all;
use function array_reverse, implode, is_array, print_r;

class WorkspaceService
{
    /**
     * @var array<string, Display>
     */
    protected array $displays = [];

    /**
     * @var array<string, Layer>
     */
    protected array $layers = [];

    /**
     * @var array<string, Window>
     */
    public array $windows = [];

    public function __construct(protected YabaiService $yabai, protected array $config, protected OutputInterface $output)
    {}

    /**
     * @return \React\Promise\PromiseInterface<array<int, \App\Services\Yabai\Display>>
     */
    protected function getAvailableDisplays(): PromiseInterface
    {
        return coroutine(function () {
            $displays = [];
            /** @var \App\Services\Yabai\Display $display */
            foreach (yield $this->yabai->getDisplays() as $display) {
                $displays[$display->index] = $display;
            }
            return $displays;
        });
    }

    /**
     * @param array<string, array{index: int, main_layer: string}> $displays
     * @param array<int, \App\Services\Yabai\Display> $available_displays
     * @return array<string, Display>
     * @throws \Exception
     */
    protected function configureDisplays(array $displays, array $available_displays): array
    {
        $list = [];
        foreach ($displays as $name => $display) {
            if (($display = $this->configureDisplay($name, $display, $available_displays)) === null) {
                continue;
            }
            $list[$name] = $display;
        }
        return $list;
    }

    protected function configureDisplay(string $name, array $config, array $available_displays): ?Display
    {
        if (!isset($config['index'])) {
            throw new Exception("Index not defined on display: {$name}");
        }
        if (!isset($config['main_layer'])) {
            throw new Exception("Main layer not defined on display: {$name}");
        }
        if (!isset($available_displays[$config['index']])) {
            $this->output->writeln("<info>Unable to find display '{$name}' [{$config['index']}]</info>");
            return null;
        }
        $this->output->writeln("Found display '{$name}' [{$config['index']}]");
        return new Display($name, $config['index'], $config['main_layer'], $available_displays[$config['index']]);
    }

    /**
     * @param array<string, array> $layers
     * @param array<string, Display> $displays
     * @return array<string, Layer>
     * @throws \Exception
     */
    protected function configureLayers(array $layers, array $displays): array
    {
        $list = [];
        foreach ($layers as $name => $config) {
            $list[$name] = $this->configureLayer($name, $config, $displays);
        }
        return $list;
    }

    /**
     * @param array{name: string, windows: array, layout: string, grid: array|null, display: string|string[]} $config
     * @param array<string, Display> $displays
     * @throws \Exception
     */
    protected function configureLayer(string $name, array $config, array $displays): Layer
    {
        if (!isset($config['windows'])) {
            throw new \Exception("No windows defined on layer: {$name}");
        }
        if (!isset($config['display'])) {
            throw new \Exception("No display defined on layer: {$name}");
        }
        $default_grid = [
            'rows' => 1,
            'cols' => 1,
            'offset-x' => 0,
            'offset-y' => 0,
            'width' => 1,
            'height' => 1
        ];
        $layout = Layout::tryFrom($config['layout'] ?? '') ?? Layout::Stack;
        $display_preference = $config['display'];
        if (!is_array($display_preference)) {
            $display_preference = [$display_preference];
        }
        $display = null;
        foreach ($display_preference as $display_name) {
            if (!isset($displays[$display_name])) {
                $this->output->writeln("  Preferred display '{$display_name}' not available, choosing another if possible");
                continue;
            }
            $this->output->writeln("  Using display '{$display_name}' for layer");
            $display = $displays[$display_name];
            break;
        }
        if ($display === null) {
            throw new Exception("Unable to find valid display for layer: {$name}");
        }
        $layer = new Layer($name, $layout, $display);
        foreach ($config['windows'] as $window_name => $window) {
            $grid = match ($layout) {
                Layout::Grid => $this->buildGrid([...$default_grid, ...($config['grid'] ?? []), ...($window['grid'] ?? [])]),
                Layout::Stack => $this->buildGrid($default_grid)
            };
            $layer->addWindow(new Window($window_name, $window['app'] ?? null, $window['title'] ?? null, $grid));
        }
        return $layer;
    }

    protected function bindWindow(Window $window, YabaiWindow $yabai_window): PromiseInterface
    {
        return coroutine(function () use ($window, $yabai_window) {
            $this->output->writeln("    Binding window {$yabai_window->app} - {$yabai_window->title} [{$yabai_window->id}]");
            $command = '-m window %s';
            $args = [(string) $yabai_window->id];
            if ($yabai_window->display !== $window->layer->display->index) {
                $command .= ' --display %s';
                $args[] = (string) $window->layer->display->index;
            }
            // verify the existing window is on the correct display
            if ($yabai_window->is_minimized) {
                $command .= ' --deminimize %s';
                $args[] = (string) $yabai_window->id;
            }
            $command .= ' --grid %s';
            $args[] = $window->yabai_grid;
            var_dump($command, $args);
            yield $this->yabai->call($command, $args);
            $window->yabai_window = yield $this->yabai->getWindow($yabai_window->id);
        });
    }

    /**
     * @param array<string, Window> $windows
     * @return \React\Promise\PromiseInterface
     */
    protected function bindExistingWindows(array $windows): PromiseInterface
    {
        return coroutine(function () use ($windows) {
            /** @var \App\Services\Yabai\Window[] $available_windows */
            $available_windows = yield $this->yabai->getWindows();
            $binds = [];
            foreach ($available_windows as $available_window) {
                foreach ($windows as $name => $window) {
                    if (!$window->matches($available_window)) {
                        continue;
                    }
                    $this->output->writeln("Found existing window for '{$name}': {$available_window->app} - {$available_window->title} [{$available_window->id}");
                    $binds[] = fn() => $this->bindWindow($window, $available_window);
                }
            }
            yield parallel($binds);
        });
    }

    protected function buildGrid(array $grid): string
    {
        $rows = $grid['rows'] ?? throw new Exception('Rows not defined on grid');
        if ($rows < 1) {
            throw new Exception('Rows must be greater than 0');
        }
        $cols = $grid['cols'] ?? throw new Exception('Cols not defined on grid');
        if ($cols < 1) {
            throw new Exception('Cols must be greater than 0');
        }
        $offset_x = $grid['offset-x'] ?? throw new Exception('Offset-x not defined on grid');
        if ($offset_x < 0) {
            throw new Exception('Offset-x must be greater than or equal to 0');
        }
        $offset_y = $grid['offset-y'] ?? throw new Exception('Offset-y not defined on grid');
        if ($offset_y < 0) {
            throw new Exception('Offset-y must be greater than or equal to 0');
        }
        $width = $grid['width'] ?? throw new Exception('Width not defined on grid');
        if ($width < 1) {
            throw new Exception('Width must be greater than or equal to 1');
        }
        $height = $grid['height'] ?? throw new Exception('Height not defined on grid');
        if ($height < 1) {
            throw new Exception('Height must be greater than or equal to 1');
        }
        if ($width + $offset_x > $cols) {
            throw new Exception("Invalid config: width [{$width}] + offset-x [{$offset_x}] must <= to the total columns [{$cols}]");
        }
        if ($height + $offset_y > $rows) {
            throw new Exception("Invalid config: height [{$height}] + offset-y [{$offset_y}] must <= to the total rows [{$rows}]");
        }
        return "{$rows}:{$cols}:{$offset_x}:{$offset_y}:{$width}:{$height}";
    }

//    protected function setupLayer(array $layer, string $layer_name, array $displays, array $existing_windows): PromiseInterface
//    {
//        return coroutine(function () use ($layer, $layer_name, $displays, $existing_windows) {
//            $this->output->writeln("Setting up layer {$layer_name}");
//            foreach ($layer['windows'] as $window_name => $window) {
//                $layer_window_name = $layer_name . '.' . $window_name;
//                $this->output->writeln("    Setting up window {$layer_window_name}");
//                $available_windows = $existing_windows[$layer_window_name] ?? null;
//                if ($available_windows === null) {
//                    $this->output->writeln("    Unable to find existing window for {$layer_window_name}");
//                    continue;
//                }
//                foreach (array_reverse($available_windows) as $available_window) {
//                    $this->output->writeln("    Configuring existing window {$available_window['app']} - {$available_window['title']} [{$available_window['id']}]");
//                    $args = ['-m', 'window', $available_window['id']];
//                    // verify the existing window is on the correct display
//                    if ($available_window['display'] !== $display['index']) {
//                        $args = [...$args, '--display', $displays[$display]['index']];
//                    }
//                    if ($available_window['is-minimized']) {
//                        $args = [...$args, '--deminimize', $available_window['id']];
//                    }
//                    $this->output->write(print_r($args, true));
//                    yield $this->yabai->call(implode(' ', $args));
//                }
//            }
//        });
//    }

    public function focusLayer(Layer $layer): PromiseInterface
    {
        return coroutine(function () use ($layer) {
            $this->output->writeln("Focusing layer: {$layer->name}");
            // @todo if already focused, change which window get focused (cycle)
            $processes = [];
            foreach (array_reverse($layer->getWindows()) as $window) {
                if ($window->yabai_window === null) {
                    $processes[] = fn() => Util::process('open -g -a %s', [$window->app]);
                    // @todo add to stack of waiting windows for yabai hook to notify us and bind window
                    continue;
                }
                $processes[] = fn() => $this->yabai->focusWindow($window->yabai_window->id);
            }
            yield parallel($processes);
        });
    }

    public function focusLayerByName(string $name): PromiseInterface
    {
        if (!isset($this->layers[$name])) {
            throw new Exception("Unable to find layer with name: {$name}");
        }
        return $this->focusLayer($this->layers[$name]);
    }

    public function setup(): PromiseInterface
    {
        return coroutine(function () {
            if (!isset($this->config['displays'])) {
                throw new \Exception('No displays defined in config');
            }
            if (!isset($this->config['layers'])) {
                throw new \Exception('No layers defined in config');
            }
            $this->displays = $this->configureDisplays($this->config['displays'], yield $this->getAvailableDisplays());
            $this->layers = $this->configureLayers($this->config['layers'], $this->displays);
            foreach ($this->layers as $layer) {
                foreach ($layer->getWindows() as $window) {
                    $this->windows["{$layer->name}.{$window->name}"] = $window;
                }
            }
            yield $this->bindExistingWindows($this->windows);

            $focuses = [];
            foreach ($this->displays as $display) {
                if (!isset($this->layers[$display->main_layer])) {
                    throw new Exception("Main layer '{$display->main_layer}' of display '{$display->name}' not found");
                }
                $focuses[] = fn() => $this->focusLayer($this->layers[$display->main_layer]);
            }
            yield parallel($focuses);
        });
    }
}
