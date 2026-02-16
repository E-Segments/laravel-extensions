<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Contracts;

/**
 * Contract for result aggregation strategies.
 */
interface ResultStrategyContract
{
    /**
     * Process results from multiple handlers.
     *
     * @param  array<mixed>  $results
     * @return mixed The aggregated result
     */
    public function aggregate(array $results): mixed;

    /**
     * Check if we should stop collecting results.
     */
    public function shouldStop(mixed $result): bool;
}
