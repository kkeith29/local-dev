<?php declare(strict_types=1);

namespace App;

use React\ChildProcess\Process;
use React\Promise\{Promise, PromiseInterface};

use function array_map, escapeshellarg, is_string, vsprintf;

class Util
{
    /**
     * @param string[] $args
     * @return \React\Promise\PromiseInterface<ProcessResult>
     */
    public static function process(string $command, array $args = [], bool $throw = true): PromiseInterface
    {
        return new Promise(function (callable $resolve, callable $reject) use ($command, $args, $throw) {
            if ($args !== []) {
                $args = array_map(fn(string|int $arg) => is_string($arg) ? escapeshellarg($arg) : $arg, $args);
                $command = vsprintf($command, $args);
            }
            $process = new Process($command);
            $process->start();
            $data = $error = '';
            $process->stdout->on('data', function (string $process_data) use (&$data): void {
                $data .= $process_data;
            });
            $process->stdout->on('error', function () {
                // @todo handle error?
            });
            $process->stderr->on('data', function (string $process_error) use (&$error): void {
                $error .= $process_error;
            });
            $process->on('exit', function (int $exit_code, ?int $term_signal) use ($command, &$data, &$error, $throw, $resolve, $reject): void {
                $result = new ProcessResult($command, $exit_code, $data, $error);
                if ($throw && !$result->isSuccessful()) {
                    $reject(new ProcessException($result, 'Unable to execute command'));
                    return;
                }
                $resolve($result);
            });
        });
    }
}