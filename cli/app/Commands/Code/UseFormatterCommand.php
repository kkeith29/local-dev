<?php

declare(strict_types=1);

namespace App\Commands\Code;

use App\Services\UseFormatterService;
use Symfony\Component\Console\{Command\Command, Input\InputInterface};
use Symfony\Component\Console\Output\{ConsoleOutputInterface, OutputInterface};
use Throwable;

/**
 * Class UseFormatterCommand
 *
 * @package App\Commands\Code
 */
class UseFormatterCommand extends Command
{
    /**
     * @var string Name of command
     */
    protected static $defaultName = 'code:use-formatter';

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
            $output->write($use_formatter->format($data));
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
