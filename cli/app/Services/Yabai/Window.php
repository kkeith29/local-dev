<?php declare(strict_types=1);

namespace App\Services\Yabai;

use Exception;

readonly class Window
{
    public static function fromArray(array $data): self
    {
        return new self(
            $data['id'] ?? throw new Exception('Id not defined'),
            $data['app'] ?? throw new Exception('App not defined'),
            $data['title'] ?? throw new Exception('Title not defined'),
            $data['display'] ?? throw new Exception('Display not defined'),
            $data['is-minimized'] ?? throw new Exception('Is-minimized not defined'),
        );
    }

    public function __construct(
        public int $id,
        public string $app,
        public string $title,
        public int $display,
        public bool $is_minimized
    ) {}
}
