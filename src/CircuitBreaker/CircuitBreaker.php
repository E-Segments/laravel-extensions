<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\CircuitBreaker;

use Esegments\Core\Concerns\Makeable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Log;

/**
 * Circuit breaker for extension handlers.
 *
 * Prevents cascading failures by temporarily disabling handlers
 * that are experiencing repeated failures.
 *
 * States:
 * - Closed: Normal operation, handler executes
 * - Open: Handler is disabled, fails fast
 * - Half-Open: Testing if handler has recovered
 *
 * @example
 * ```php
 * $circuitBreaker = new CircuitBreaker($cache, threshold: 5, timeout: 60);
 *
 * if ($circuitBreaker->isAvailable(MyHandler::class)) {
 *     try {
 *         // Execute handler
 *         $circuitBreaker->recordSuccess(MyHandler::class);
 *     } catch (Throwable $e) {
 *         $circuitBreaker->recordFailure(MyHandler::class);
 *         throw $e;
 *     }
 * }
 * ```
 */
final class CircuitBreaker
{
    use Makeable;

    private CircuitBreakerStore $store;

    public function __construct(
        CacheRepository $cache,
        private readonly int $threshold = 5,
        private readonly int $timeout = 60,
        private readonly int $halfOpenMax = 3,
        private readonly bool $enabled = true,
        private readonly ?string $logChannel = null,
    ) {
        $this->store = new CircuitBreakerStore($cache, $timeout);
    }

    /**
     * Check if a handler is available for execution.
     */
    public function isAvailable(string $handlerClass): bool
    {
        if (! $this->enabled) {
            return true;
        }

        $state = $this->store->getState($handlerClass);

        return $state->allowsRequests();
    }

    /**
     * Record a successful handler execution.
     */
    public function recordSuccess(string $handlerClass): void
    {
        if (! $this->enabled) {
            return;
        }

        $state = $this->store->getState($handlerClass);

        if ($state === CircuitState::HalfOpen) {
            $this->store->recordSuccess($handlerClass);

            // Check if we've had enough successful attempts to close the circuit
            if ($this->store->getHalfOpenAttempts($handlerClass) >= $this->halfOpenMax) {
                $this->close($handlerClass);
            }
        }
    }

    /**
     * Record a handler failure.
     */
    public function recordFailure(string $handlerClass): void
    {
        if (! $this->enabled) {
            return;
        }

        $state = $this->store->getState($handlerClass);
        $failureCount = $this->store->recordFailure($handlerClass);

        // If in half-open state, immediately re-open the circuit
        if ($state === CircuitState::HalfOpen) {
            $this->open($handlerClass);
            $this->log('warning', "Circuit breaker re-opened for [{$handlerClass}] after half-open failure");

            return;
        }

        // Check if we've exceeded the threshold
        if ($failureCount >= $this->threshold) {
            $this->open($handlerClass);
            $this->log('warning', "Circuit breaker opened for [{$handlerClass}] after {$failureCount} failures");
        }
    }

    /**
     * Open the circuit (disable handler).
     */
    public function open(string $handlerClass): void
    {
        $this->store->setState($handlerClass, CircuitState::Open);
    }

    /**
     * Close the circuit (enable handler).
     */
    public function close(string $handlerClass): void
    {
        $this->store->setState($handlerClass, CircuitState::Closed);
        $this->store->reset($handlerClass);
        $this->log('info', "Circuit breaker closed for [{$handlerClass}]");
    }

    /**
     * Get the current state of a handler's circuit.
     */
    public function status(string $handlerClass): CircuitState
    {
        return $this->store->getState($handlerClass);
    }

    /**
     * Get the failure count for a handler.
     */
    public function failureCount(string $handlerClass): int
    {
        return $this->store->getFailureCount($handlerClass);
    }

    /**
     * Reset a handler's circuit.
     */
    public function reset(string $handlerClass): void
    {
        $this->store->reset($handlerClass);
    }

    /**
     * Check if the circuit breaker is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Log a circuit breaker event.
     */
    private function log(string $level, string $message): void
    {
        $logger = $this->logChannel
            ? Log::channel($this->logChannel)
            : Log::getFacadeRoot();

        $logger->$level("[CircuitBreaker] {$message}");
    }
}
