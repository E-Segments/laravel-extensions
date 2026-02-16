<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Strategies;

use Closure;
use Esegments\LaravelExtensions\Contracts\ResultStrategyContract;

/**
 * Strategy that reduces all results using a callback.
 *
 * @example
 * ```php
 * $total = Extensions::reduceResults(fn ($carry, $result) => $carry + $result, 0)
 *     ->dispatch(new CalculateTotal($items));
 * ```
 */
class ReduceResultsStrategy implements ResultStrategyContract
{
    protected Closure $reducer;

    protected mixed $initial;

    public function __construct(
        callable $reducer,
        mixed $initial = null,
    ) {
        $this->reducer = $reducer(...);
        $this->initial = $initial;
    }

    /**
     * {@inheritDoc}
     */
    public function aggregate(array $results): mixed
    {
        $carry = $this->initial;

        foreach ($results as $result) {
            if ($result !== null) {
                $carry = ($this->reducer)($carry, $result);
            }
        }

        return $carry;
    }

    /**
     * {@inheritDoc}
     */
    public function shouldStop(mixed $result): bool
    {
        // Never stop - collect all results
        return false;
    }

    /**
     * Create a sum strategy.
     */
    public static function sum(int|float $initial = 0): static
    {
        return new static(
            fn ($carry, $result) => $carry + (is_numeric($result) ? $result : 0),
            $initial,
        );
    }

    /**
     * Create a concatenation strategy.
     */
    public static function concat(string $separator = ''): static
    {
        return new static(
            fn ($carry, $result) => $carry . $separator . (string) $result,
            '',
        );
    }

    /**
     * Create an all-true strategy (logical AND).
     */
    public static function allTrue(): static
    {
        return new static(
            fn ($carry, $result) => $carry && (bool) $result,
            true,
        );
    }

    /**
     * Create an any-true strategy (logical OR).
     */
    public static function anyTrue(): static
    {
        return new static(
            fn ($carry, $result) => $carry || (bool) $result,
            false,
        );
    }

    /**
     * Create a count strategy.
     */
    public static function count(): static
    {
        return new static(
            fn ($carry, $result) => $carry + 1,
            0,
        );
    }

    /**
     * Create a min strategy.
     */
    public static function min(): static
    {
        return new static(
            fn ($carry, $result) => $carry === null || $result < $carry ? $result : $carry,
            null,
        );
    }

    /**
     * Create a max strategy.
     */
    public static function max(): static
    {
        return new static(
            fn ($carry, $result) => $carry === null || $result > $carry ? $result : $carry,
            null,
        );
    }
}
