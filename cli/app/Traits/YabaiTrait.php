<?php declare(strict_types=1);

namespace App\Traits;

use Symfony\Component\Process\Process;

use function json_decode;

use const JSON_THROW_ON_ERROR;

trait YabaiTrait
{
    public function call(array $command, bool $throw = true): ?Process
    {
        $command = ['yabai', ...$command];
        $process = new Process($command, env: [], timeout: 10);
        if ($throw) {
            return $process->mustRun();
        }
        $process->run();
        return $process;
    }

    public function query(array $command, bool $throw = true): ?array
    {
        $process = $this->call(['-m', 'query', ...$command], $throw);
        if (!$throw && !$process->isSuccessful()) {
            return null;
        }
        return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    }
}
