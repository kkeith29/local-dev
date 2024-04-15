<?php declare(strict_types=1);

namespace App\Commands\Code;

use App\Traits\YabaiTrait;
use Symfony\Component\Console\{Attribute\AsCommand, Command\Command, Input\InputInterface};
use Symfony\Component\Console\Output\{ConsoleOutputInterface, OutputInterface};
use Throwable;

use function is_numeric;

#[AsCommand('yabai:app-activated')]
class YabaiAppActivatedCommand extends Command
{
    use YabaiTrait;

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $yabai_pid = $_ENV['YABAI_PROCESS_ID'] ?? null;
            if (!is_numeric($yabai_pid)) {
                $output->writeln('No YABAI_PROCESS_ID env var defined');
                return Command::FAILURE;
            }
            $yabai_pid = (int) $yabai_pid;
            $windows = $this->query(['--windows']);
            $minimized = [];
            foreach ($windows as $window) {
                if (($window['pid'] ?? null) !== $yabai_pid) {
                    continue;
                }
                if (!$window['is-minimized']) {
                    $output->writeln("Found non-minimized {$window['app']} window");
                    return Command::SUCCESS;
                }
                $minimized[] = $window;
            }
            if ($minimized === []) {
                $output->writeln('No minimized windows found');
                return Command::SUCCESS;
            }
            foreach ($minimized as $window) {
                $output->writeln("Deminimizing window: {$window['app']} - {$window['title']} [{$window['id']}]");
                $this->call(['-m', 'window', '--deminimize', $window['id']]);
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
