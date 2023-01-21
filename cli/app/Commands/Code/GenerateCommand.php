<?php declare(strict_types=1);

namespace App\Commands\Code;

use App\Services\CodeGeneratorService;
use App\Services\CodeGenerator\Enums\FileType;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputArgument, InputInterface, InputOption};
use Symfony\Component\Console\Output\{ConsoleOutputInterface, OutputInterface};
use Throwable;

/**
 * Class GenerateCommand
 *
 * @package App\Commands\Code
 */
class GenerateCommand extends Command
{
    /**
     * @var string Name of command
     */
    protected static $defaultName = 'code:generate';

    /**
     * Configure command
     *
     * @return void
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     */
    public function configure(): void
    {
        $this->addArgument('file_path', InputArgument::REQUIRED, 'File path to generate code for');
        $this->addOption('type', null, InputOption::VALUE_REQUIRED, 'Type of file to generate');
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
        $file_path = $input->getArgument('file_path');
        try {
            if (($type = $input->getOption('type')) !== null) {
                $type = FileType::from($type);
            }
            $generator = new CodeGeneratorService();
            $output->write($generator->generate($file_path, $type));
            return Command::SUCCESS;
        } catch (Throwable $e) {
            if ($output instanceof ConsoleOutputInterface) {
                $output->getErrorOutput()->writeln(">>> {$e->getMessage()}");
            }
            return Command::FAILURE;
        }
    }
}
