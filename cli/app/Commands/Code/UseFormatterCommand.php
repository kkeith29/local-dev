<?php declare(strict_types=1);

namespace App\Commands\Code;

use App\Services\UseFormatterService;
use Exception;
use Symfony\Component\Console\{Attribute\AsCommand, Command\Command};
use Symfony\Component\Console\Input\{InputInterface, InputOption};
use Symfony\Component\Console\Output\{ConsoleOutputInterface, OutputInterface};
use Throwable;

#[AsCommand('code:use-formatter')]
class UseFormatterCommand extends Command
{
    /**
     * Configure options for command
     */
    protected function configure(): void
    {
        $this->addOption('max-line-length', null, InputOption::VALUE_REQUIRED, default: 120);
        $this->addOption('min-sibling-group-count', null, InputOption::VALUE_REQUIRED, default: 2);
        $this->addOption('max-group-depth', null, InputOption::VALUE_REQUIRED, default: 2);
    }

    /**
     * Execute command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $data = stream_get_contents(STDIN);
        try {
            $use_formatter = new UseFormatterService();
            $max_line_length = (int) $input->getOption('max-line-length');
            if ($max_line_length <= 0) {
                throw new Exception('Max line length must be a number greater than 0');
            }
            $min_sibling_group_count = (int) $input->getOption('min-sibling-group-count');
            if ($min_sibling_group_count <= 0) {
                throw new Exception('Min sibling group count must be a number greater than 0');
            }
            $max_group_depth = (int) $input->getOption('max-group-depth');
            if ($max_group_depth <= 0) {
                throw new Exception('Max group depth must be a number greater than 0');
            }
            $output->write($use_formatter->format($data, $max_line_length, $min_sibling_group_count, $max_group_depth));
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
