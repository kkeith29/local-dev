<?php declare(strict_types=1);

namespace App;

readonly class ProcessResult
{
    public function __construct(public string $command, public int $exit_code, public string $data, public string $error)
    {}

    public function isSuccessful(): bool
    {
        return $this->exit_code === 0;
    }
}
