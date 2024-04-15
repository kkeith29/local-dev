<?php declare(strict_types=1);

namespace App\Services;

use App\{ProcessResult, Util};
use App\Services\Yabai\{Display, Window};
use React\Promise\PromiseInterface;

use function React\Async\coroutine;
use function array_map, json_decode;

use const JSON_THROW_ON_ERROR;

class YabaiService
{
    public function call(string $command, array $args, bool $throw = true): PromiseInterface
    {
        return Util::process('yabai ' . $command, $args, $throw);
    }

    /**
     * @param string[] $args
     * @return \React\Promise\PromiseInterface<array>
     */
    public function query(string $command, array $args = [], bool $throw = true): PromiseInterface
    {
        return coroutine(function () use ($command, $args, $throw) {
            /** @var ProcessResult $process */
            $process = yield Util::process('yabai -m query ' . $command, $args, $throw);
            if (!$throw && !$process->isSuccessful()) {
                return null;
            }
            return json_decode($process->data, true, flags: JSON_THROW_ON_ERROR);
        });
    }

    /**
     * @return \React\Promise\PromiseInterface<\App\Services\Yabai\Display[]>
     */
    public function getDisplays(): PromiseInterface
    {
        return coroutine(function () {
            return array_map(
                fn(array $display): Display => Display::fromArray($display),
                yield $this->query('--displays')
            );
        });
    }

    public function getDisplay(int|string|null $selector = null): PromiseInterface
    {
        return coroutine(function () use ($selector) {
            $command = '--displays --display';
            $args = [];
            if ($selector !== null) {
                $command .= ' %s';
                $args[] = (string) $selector;
            }
            return Display::fromArray(yield $this->query($command, $args));
        });
    }

    public function getWindows(): PromiseInterface
    {
        return coroutine(function () {
            return array_map(
                fn(array $window): Window => Window::fromArray($window),
                yield $this->query('--windows')
            );
        });
    }

    public function getWindow(int|string|null $selector = null): PromiseInterface
    {
        return coroutine(function () use ($selector) {
            $command = '--windows --window';
            $args = [];
            if ($selector !== null) {
                $command .= ' %s';
                $args[] = (string) $selector;
            }
            return Window::fromArray(yield $this->query($command, $args));
        });
    }

    public function focusWindow(int|string $selector): PromiseInterface
    {
        return $this->call('-m window %s --focus', [(string) $selector]);
    }
}
