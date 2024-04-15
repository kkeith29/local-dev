<?php declare(strict_types=1);

namespace App\Services\Workspace;

use App\Services\Yabai\Display as YabaiDisplay;

class Display
{
    public function __construct(
        public string $name,
        public int $index,
        public string $main_layer,
        public YabaiDisplay $yabai_display
    ) {}
}
