<?php declare(strict_types=1);

namespace App\Services\Workspace;

use Exception;

class Layer
{
    protected array $windows = [];

    public function __construct(public string $name, public Layout $layout, public Display $display)
    {}

    public function addWindow(Window $window): void
    {
        if (isset($this->windows[$window->name])) {
            throw new Exception("Window with name {$window->name} already exists in layer: {$this->name}");
        }
        $this->windows[$window->name] = $window;
        $window->layer = $this;
    }

    /**
     * @return array<string, \App\Services\Workspace\Window>
     */
    public function getWindows(): array
    {
        return $this->windows;
    }
}
