<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Strategies;

use Esegments\LaravelExtensions\Contracts\ResultStrategyContract;

/**
 * Strategy that returns the first non-null result.
 *
 * @example
 * ```php
 * $result = Extensions::firstResult()->dispatch(new ResolvePrice($product));
 * ```
 */
class FirstResultStrategy implements ResultStrategyContract
{
    protected mixed $firstResult = null;

    protected bool $foundResult = false;

    /**
     * {@inheritDoc}
     */
    public function aggregate(array $results): mixed
    {
        foreach ($results as $result) {
            if ($result !== null) {
                return $result;
            }
        }

        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function shouldStop(mixed $result): bool
    {
        if (! $this->foundResult && $result !== null) {
            $this->firstResult = $result;
            $this->foundResult = true;

            return true;
        }

        return false;
    }

    /**
     * Get the first result found.
     */
    public function getFirstResult(): mixed
    {
        return $this->firstResult;
    }

    /**
     * Reset the strategy state.
     */
    public function reset(): void
    {
        $this->firstResult = null;
        $this->foundResult = false;
    }
}
