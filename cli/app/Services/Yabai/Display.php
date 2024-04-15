<?php declare(strict_types=1);

namespace App\Services\Yabai;

use Exception;

readonly class Display
{
    public static function fromArray(array $data): self
    {
        return new self(
            $data['index'] ?? throw new Exception('Index not defined')
        );
    }

    public function __construct(
        public int $index
    ) {}
}
