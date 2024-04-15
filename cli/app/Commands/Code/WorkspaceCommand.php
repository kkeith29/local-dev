<?php declare(strict_types=1);

namespace App\Commands\Code;

use App\ProcessException;
use App\Services\Workspace\Connection;
use App\Services\WorkspaceService;
use App\Services\YabaiService;
use Exception;
use React\EventLoop\Loop;
use React\Socket\{ConnectionInterface, LimitingServer, UnixServer};
use Symfony\Component\Console\{Attribute\AsCommand, Command\Command, Input\InputInterface};
use Symfony\Component\Console\Output\{ConsoleOutputInterface, OutputInterface};
use React\Promise\PromiseInterface;
use Throwable;
use function file_exists;
use function is_array;
use function is_file;
use function React\Async\coroutine;
use function unlink;
use const PATH_CONFIG;
use const SIGTERM;

#[AsCommand('workspace')]
class WorkspaceCommand extends Command
{
    protected LimitingServer $server;

    protected WorkspaceService $service;

    protected function handleLayerSelect(array $payload): PromiseInterface
    {
        return coroutine(function () use ($payload) {
            $name = $payload['name'] ?? throw new Exception('Layer name not defined in payload');
            yield $this->service->focusLayerByName($name);
            return null;
        });
    }

    protected function handleMessage(Connection $connection, array $message): PromiseInterface
    {
        return coroutine(function () use ($connection, $message) {
            if (!isset($message['type'])) {
                throw new Exception('Type not defined in message');
            }
            $message['payload'] ??= [];
            $response = match ($message['type']) {
                'layer-select' => yield $this->handleLayerSelect($message['payload'])
            };
            $connection->sendSuccess($response);
        });
    }

    protected function handleConnection(ConnectionInterface $connection, OutputInterface $output): void
    {
        $output->writeln('Client connected');
        $connection = new Connection($connection, $output);
        $connection->on('message', function (array $message) use ($output, $connection): void {
            $this->handleMessage($connection, $message)->catch(function (Throwable $e) use ($connection, $output) {
                // @todo handle error and send to client
                $output->write((string) $e);
            });
        });
        $connection->on('message-error', function (Throwable $e) use ($output, $connection): void {
            $connection->sendError('Unable to parse message', 'message');
            // @todo log error
        });
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $sock_path = '/tmp/ld_server.sock';
            if (is_file($sock_path)) {
                $output->writeln("<error>Socket already running at {$sock_path}</error>");
                return Command::FAILURE;
            }

            $config_path = PATH_CONFIG . 'workspace.php';
            if (!file_exists($config_path)) {
                $output->writeln("<error>Config file {$config_path} not found</error>");
                return Command::FAILURE;
            }
            $config = include($config_path);
            if (!is_array($config)) {
                $output->writeln("<error>Config file {$config_path} did not return an array</error>");
                return Command::FAILURE;
            }

            $yabai_service = new YabaiService();
            $this->service = new WorkspaceService($yabai_service, $config, $output);
            $this->service->setup()->catch(function (Throwable $e) use ($output) {
                $output->writeln('<error>Unable to setup service</error>');
                if ($e instanceof ProcessException) {
                    $output->writeln("  Command: {$e->result->command}");
                    $output->writeln("  Exit Code: {$e->result->exit_code}");
                    $output->writeln("  Output:");
                    $output->writeln('');
                    $output->write($e->result->data);
                    $output->writeln("  Error:");
                    $output->writeln('');
                    $output->write($e->result->error);
                }
                $output->write((string) $e);
            });

            $sig_handler = function () use ($output, $sock_path): void {
                $output->writeln('Cleaning up...');
                if (!@unlink($sock_path)) {
                    $output->writeln("<error>Unable to delete socket file: {$sock_path}</error>");
                }
                Loop::stop();
            };
            Loop::addSignal(SIGTERM, $sig_handler);
            Loop::addSignal(SIGINT, $sig_handler);
            $this->server = new LimitingServer(new UnixServer($sock_path), 20);
            $output->writeln('Listening to socket at ' . $sock_path);
            $this->server->on('connection', function (ConnectionInterface $connection) use ($output): void {
                $this->handleConnection($connection, $output);
            });
            return Command::SUCCESS;
        } catch (Throwable $e) {
            if ($output instanceof ConsoleOutputInterface) {
                $output->getErrorOutput()->writeln(">>> {$e->getMessage()}");
            }
            return Command::FAILURE;
        }
    }
}
