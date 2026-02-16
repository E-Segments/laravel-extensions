<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Attributes;

use Attribute;

/**
 * Configure async behavior for an extension handler.
 *
 * @example
 * ```php
 * #[ExtensionHandler(OrderPlaced::class)]
 * #[Async(
 *     queue: 'high-priority',
 *     delay: 60,
 *     retries: 3,
 *     backoff: 'exponential',
 *     timeout: 120,
 *     onFailure: NotifyAdmin::class,
 * )]
 * class ProcessOrderAsync implements AsyncHandlerContract { }
 * ```
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Async
{
    /**
     * @param  string|null  $queue  The queue to dispatch the handler to
     * @param  int  $delay  Seconds to delay before processing
     * @param  int  $retries  Maximum number of retry attempts
     * @param  string  $backoff  Backoff strategy: 'fixed', 'linear', 'exponential'
     * @param  int|array<int>  $backoffSeconds  Seconds between retries (or array for each retry)
     * @param  int|null  $timeout  Maximum execution time in seconds
     * @param  class-string|null  $onFailure  Handler class to call on final failure
     * @param  class-string|null  $onRetry  Handler class to call on each retry
     * @param  bool  $uniqueJob  Whether to ensure only one instance runs at a time
     * @param  int|null  $uniqueLockTimeout  Seconds to hold the unique lock
     */
    public function __construct(
        public readonly ?string $queue = null,
        public readonly int $delay = 0,
        public readonly int $retries = 3,
        public readonly string $backoff = 'exponential',
        public readonly int|array $backoffSeconds = 10,
        public readonly ?int $timeout = null,
        public readonly ?string $onFailure = null,
        public readonly ?string $onRetry = null,
        public readonly bool $uniqueJob = false,
        public readonly ?int $uniqueLockTimeout = null,
    ) {}

    /**
     * Calculate the backoff time for a given attempt.
     */
    public function calculateBackoff(int $attempt): int
    {
        if (is_array($this->backoffSeconds)) {
            return $this->backoffSeconds[$attempt - 1] ?? end($this->backoffSeconds);
        }

        return match ($this->backoff) {
            'linear' => $this->backoffSeconds * $attempt,
            'exponential' => (int) ($this->backoffSeconds * pow(2, $attempt - 1)),
            default => $this->backoffSeconds, // 'fixed'
        };
    }

    /**
     * Get the backoff array for Laravel's job backoff.
     *
     * @return array<int>
     */
    public function getBackoffArray(): array
    {
        if (is_array($this->backoffSeconds)) {
            return $this->backoffSeconds;
        }

        $backoffs = [];
        for ($i = 1; $i <= $this->retries; $i++) {
            $backoffs[] = $this->calculateBackoff($i);
        }

        return $backoffs;
    }
}
