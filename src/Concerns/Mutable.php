<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Concerns;

/**
 * Trait for muting specific handlers.
 *
 * When a handler is muted, it will be skipped during dispatch
 * but other handlers will continue to execute normally.
 *
 * @example
 * ```php
 * // Mute a specific handler
 * Extensions::mute(AuditHandler::class);
 *
 * // Handler will be skipped
 * Extensions::dispatch(new UserCreated($user));
 *
 * // Unmute the handler
 * Extensions::unmute(AuditHandler::class);
 * ```
 */
trait Mutable
{
    /**
     * Muted handlers indexed by class name.
     *
     * @var array<string, true>
     */
    private array $mutedHandlers = [];

    /**
     * Mute a specific handler.
     *
     * @param  class-string  $handlerClass
     * @return $this
     */
    public function mute(string $handlerClass): self
    {
        $this->mutedHandlers[$handlerClass] = true;

        return $this;
    }

    /**
     * Unmute a specific handler.
     *
     * @param  class-string  $handlerClass
     * @return $this
     */
    public function unmute(string $handlerClass): self
    {
        unset($this->mutedHandlers[$handlerClass]);

        return $this;
    }

    /**
     * Check if a handler is muted.
     *
     * @param  class-string  $handlerClass
     */
    public function isMuted(string $handlerClass): bool
    {
        return isset($this->mutedHandlers[$handlerClass]);
    }

    /**
     * Get all muted handlers.
     *
     * @return array<string>
     */
    public function getMutedHandlers(): array
    {
        return array_keys($this->mutedHandlers);
    }

    /**
     * Clear all muted handlers.
     *
     * @return $this
     */
    public function clearMuted(): self
    {
        $this->mutedHandlers = [];

        return $this;
    }

    /**
     * Execute a callback with a handler muted.
     *
     * @template T
     *
     * @param  class-string  $handlerClass
     * @param  callable(): T  $callback
     * @return T
     */
    public function withMuted(string $handlerClass, callable $callback): mixed
    {
        $wasMuted = $this->isMuted($handlerClass);
        $this->mute($handlerClass);

        try {
            return $callback();
        } finally {
            if (! $wasMuted) {
                $this->unmute($handlerClass);
            }
        }
    }

    /**
     * Execute a callback with multiple handlers muted.
     *
     * @template T
     *
     * @param  array<class-string>  $handlerClasses
     * @param  callable(): T  $callback
     * @return T
     */
    public function withMutedMany(array $handlerClasses, callable $callback): mixed
    {
        $previouslyMuted = [];

        foreach ($handlerClasses as $handlerClass) {
            $previouslyMuted[$handlerClass] = $this->isMuted($handlerClass);
            $this->mute($handlerClass);
        }

        try {
            return $callback();
        } finally {
            foreach ($handlerClasses as $handlerClass) {
                if (! $previouslyMuted[$handlerClass]) {
                    $this->unmute($handlerClass);
                }
            }
        }
    }
}
