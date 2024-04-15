<?php declare(strict_types=1);

namespace App\Commands\Code;

use App\Services\Workspace\Connection;
use App\Traits\YabaiTrait;
use React\Socket\{ConnectionInterface, UnixConnector};
use Symfony\Component\Console\{Attribute\AsCommand, Command\Command};
use Symfony\Component\Console\Input\{InputArgument, InputInterface};
use Symfony\Component\Console\Output\{ConsoleOutputInterface, OutputInterface};
use Throwable;

use function print_r;

#[AsCommand('workspace:layer-select')]
class WorkspaceLayerSelectCommand extends Command
{
    use YabaiTrait;

    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'Layer to select');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            (new UnixConnector())->connect('/tmp/ld_server.sock')->then(function (ConnectionInterface $connection) use ($input, $output): void {
                $connection = new Connection($connection, $output);
                $connection->on('message', function (array $message) use ($output) {
                    $output->write(print_r($message, true)); // @todo handle response
                });
                $output->writeln('Sending data...');
                $connection->send([
                    'type' => 'layer-select',
                    'payload' => [
                        'name' => $input->getArgument('name')
                    ]
                ]);
            });

            return Command::SUCCESS;
        } catch (Throwable $e) {
            if ($output instanceof ConsoleOutputInterface) {
                $output->getErrorOutput()->writeln(">>> {$e->getMessage()}");
                $output->write((string) $e);
            }
            return Command::FAILURE;
        }
    }
}
