<?php declare(strict_types=1);

namespace App\Services\Workspace;

use App\Services\Yabai\Window as YabaiWindow;
use Exception;

class Window
{
    public Layer $layer;

    public function __construct(
        public string $name,
        public ?string $app,
        public ?string $title,
        public string $yabai_grid,
        public ?YabaiWindow $yabai_window = null
    ) {
        if ($this->app === null && $this->title === null) {
            throw new Exception("App and/or title required for window: {$this->name}");
        }
    }

    public function setLayer(Layer $layer): void
    {
        $this->layer = $layer;
    }

    public function matches(YabaiWindow $window): bool
    {
        // @todo add regex matching
        return (
            ($this->app !== null && $this->app === $window->app)
            || ($this->title !== null && $this->title === $window->title)
        );
    }
}
