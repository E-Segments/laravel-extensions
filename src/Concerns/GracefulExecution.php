<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Concerns;

/**
 * Trait for graceful execution mode.
 *
 * When graceful mode is enabled, handler errors are caught and collected
 * rather than thrown, allowing all handlers to execute even if some fail.
 *
 * @example
 * ```php
 * // Enable graceful mode
 * Extensions::gracefully()->dispatch(new UserCreated($user));
 *
 * // Or get detailed results including errors
 * $result = Extensions::gracefully()->dispatchWithResults($event);
 * if ($result->hasErrors()) {
 *     foreach ($result->errors() as $handlerClass => $error) {
 *         Log::error("Handler {$handlerClass} failed: {$error->getMessage()}");
 *     }
 * }
 * ```
 */
trait GracefulExecution
{
    /**
     * Whether graceful mode is enabled.
     */
    private bool $gracefulMode = false;

    /**
     * Enable graceful mode for the next dispatch.
     *
     * @return $this
     */
    public function gracefully(): self
    {
        $this->gracefulMode = true;

        return $this;
    }

    /**
     * Disable graceful mode.
     *
     * @return $this
     */
    public function strictly(): self
    {
        $this->gracefulMode = false;

        return $this;
    }

    /**
     * Check if graceful mode is enabled.
     */
    public function isGracefulMode(): bool
    {
        return $this->gracefulMode;
    }

    /**
     * Reset graceful mode to the configured default.
     *
     * @return $this
     */
    public function resetGracefulMode(): self
    {
        $this->gracefulMode = (bool) config('extensions.graceful_mode', false);

        return $this;
    }
}
