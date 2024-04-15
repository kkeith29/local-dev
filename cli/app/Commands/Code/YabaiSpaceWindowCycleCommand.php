<?php declare(strict_types=1);

namespace App\Commands\Code;

use App\Traits\YabaiTrait;
use Exception;
use Symfony\Component\Console\{Attribute\AsCommand, Command\Command};
use Symfony\Component\Console\Input\{InputArgument, InputInterface};
use Symfony\Component\Console\Output\{ConsoleOutputInterface, OutputInterface};
use Throwable;

use function array_filter, array_map, array_pop, array_shift, count;

#[AsCommand('yabai:space-window-cycle')]
class YabaiSpaceWindowCycleCommand extends Command
{
    use YabaiTrait;

    public function configure(): void
    {
        $this->addArgument('direction', InputArgument::REQUIRED, 'Direction to cycle windows in on space');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $forward = $input->getArgument('direction') === 'next';
            $current_space = $this->query(['--spaces', '--space']);
            $space_windows = [];
            foreach ($this->query(['--windows', '--space', $current_space['index']]) as $window) {
                $space_windows[$window['id']] = $window;
            }
            $windows = array_map(function (int $window_id) use ($space_windows): array {
                return $space_windows[$window_id] ?? throw new Exception('Unable to find space window with id: ' . $window_id);
            }, $current_space['windows']);
            $windows = array_filter($windows, fn(array $window): bool => !$window['is-minimized']);
            $window_count = count($windows);
            if ($window_count === 0) {
                $output->writeln('No focusable windows found on current space');
                return Command::SUCCESS;
            }
            $current_window = array_shift($windows);
            if ($windows === []) {
                if (!$current_window['has-focus']) {
                    $this->call(['-m', 'window', $current_window['id'], '--focus']);
                }
                $output->writeln('Only 1 window found on space, nothing to cycle to');
                return Command::SUCCESS;
            }
            $next_window = $forward ? array_shift($windows) : array_pop($windows);

            $output->writeln("Focusing window {$next_window['app']} - {$next_window['title']} [{$next_window['id']}");
            $this->call(['-m', 'window', $next_window['id'], '--focus']);
            if ($windows !== []) {
                $last_window = $forward ? array_pop($windows) : array_shift($windows);
                $this->call(['-m', 'window', $current_window['id'], $forward ? '--lower' : '--raise', $last_window['id']]);
            }
            return Command::SUCCESS;
        } catch (Throwable $e) {
            if ($output instanceof ConsoleOutputInterface) {
                $output->getErrorOutput()->writeln(">>> {$e->getMessage()}");
            }
            return Command::FAILURE;
        }
    }
}
