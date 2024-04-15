<?php declare(strict_types=1);

namespace App\Commands\Code;

use App\Traits\YabaiTrait;
use Exception;
use Symfony\Component\Console\{Attribute\AsCommand, Command\Command};
use Symfony\Component\Console\Input\{InputArgument, InputInterface};
use Symfony\Component\Console\Output\{ConsoleOutputInterface, OutputInterface};
use Throwable;

use function array_filter, array_map, array_shift;

#[AsCommand('yabai:space-window-minimize')]
class YabaiSpaceWindowMinimizeCommand extends Command
{
    use YabaiTrait;

    public function configure(): void
    {
        $this->addArgument('action', InputArgument::REQUIRED, 'Action to perform (minimize, deminimize)');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $minimize = $input->getArgument('action') === 'minimize';
            $current_space = $this->query(['--spaces', '--space']);
            $space_windows = $this->query(['--windows', '--space', $current_space['index']]);
            if ($minimize) {
                if ($space_windows === []) {
                    $output->writeln('No windows available to minimize');
                    return Command::SUCCESS;
                }
                $current_window = array_shift($space_windows);
                if ($current_window['is-minimized']) {
                    $output->writeln("Current window {$current_window['app']} - {$current_window['title']} [{$current_window['id']} is already minimized");
                    return Command::SUCCESS;
                }
                $output->writeln("Minimizing window {$current_window['app']} - {$current_window['title']} [{$current_window['id']}");
                $this->call(['-m', 'window', $current_window['id'], '--minimize']);
                if ($space_windows !== []) {
                    $next_window = array_shift($space_windows);
                    if ($next_window['is-minimized']) {
                        $output->writeln('Next window is minimized, not focusing');
                        return Command::SUCCESS;
                    }
                    $output->writeln("Focusing next window {$next_window['app']} - {$next_window['title']} [{$next_window['id']}");
                    $this->call(['-m', 'window', $next_window['id'], '--focus']);
                }
                return Command::SUCCESS;
            }
            $space_windows = array_filter($space_windows, fn(array $window): bool => $window['is-minimized']);
            if ($space_windows === []) {
                $output->writeln('No minimized windows found on current space');
                return Command::SUCCESS;
            }
            $first_window = array_shift($space_windows);
            $output->writeln("Deminimizing window {$first_window['app']} - {$first_window['title']} [{$first_window['id']}");
            $this->call(['-m', 'window', '--focus', $first_window['id']]);
            return Command::SUCCESS;
        } catch (Throwable $e) {
            if ($output instanceof ConsoleOutputInterface) {
                $output->getErrorOutput()->writeln(">>> {$e->getMessage()}");
            }
            return Command::FAILURE;
        }
    }
}
