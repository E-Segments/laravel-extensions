<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Concerns;

/**
 * Trait for silencing all extension dispatching.
 *
 * When silenced, no handlers are executed for any extension point.
 * This is useful during bulk operations like seeding or imports.
 *
 * @example
 * ```php
 * Extensions::silenceAll();
 * // No handlers will execute
 * User::factory()->count(1000)->create();
 * Extensions::resumeAll();
 *
 * // Or use the closure form:
 * Extensions::silence(function () {
 *     User::factory()->count(1000)->create();
 * });
 * ```
 */
trait Silenceable
{
    /**
     * Whether all dispatching is silenced.
     */
    private bool $silenced = false;

    /**
     * Silence all dispatching and execute a callback.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function silence(callable $callback): mixed
    {
        $wasSilenced = $this->silenced;
        $this->silenced = true;

        try {
            return $callback();
        } finally {
            $this->silenced = $wasSilenced;
        }
    }

    /**
     * Silence all dispatching globally.
     *
     * @return $this
     */
    public function silenceAll(): self
    {
        $this->silenced = true;

        return $this;
    }

    /**
     * Resume all dispatching.
     *
     * @return $this
     */
    public function resumeAll(): self
    {
        $this->silenced = false;

        return $this;
    }

    /**
     * Check if dispatching is silenced.
     */
    public function isSilenced(): bool
    {
        return $this->silenced;
    }
}
