<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Strategies;

use Esegments\LaravelExtensions\Contracts\ResultStrategyContract;
use Illuminate\Support\Collection;

/**
 * Strategy that merges all results into a collection or array.
 *
 * @example
 * ```php
 * $results = Extensions::mergeResults()->dispatch(new CollectValidation($data));
 * ```
 */
class MergeResultsStrategy implements ResultStrategyContract
{
    protected bool $asCollection;

    protected bool $flattenArrays;

    public function __construct(
        bool $asCollection = true,
        bool $flattenArrays = true,
    ) {
        $this->asCollection = $asCollection;
        $this->flattenArrays = $flattenArrays;
    }

    /**
     * {@inheritDoc}
     *
     * @return Collection|array
     */
    public function aggregate(array $results): Collection|array
    {
        $merged = [];

        foreach ($results as $result) {
            if ($result === null) {
                continue;
            }

            if ($this->flattenArrays && is_array($result)) {
                $merged = array_merge($merged, $result);
            } elseif ($this->flattenArrays && $result instanceof Collection) {
                $merged = array_merge($merged, $result->all());
            } else {
                $merged[] = $result;
            }
        }

        return $this->asCollection ? collect($merged) : $merged;
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
     * Return results as array instead of collection.
     */
    public function asArray(): static
    {
        $this->asCollection = false;

        return $this;
    }

    /**
     * Don't flatten nested arrays.
     */
    public function preserveNesting(): static
    {
        $this->flattenArrays = false;

        return $this;
    }
}
