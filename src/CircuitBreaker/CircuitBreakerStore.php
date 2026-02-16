<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\CircuitBreaker;

use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Storage for circuit breaker state.
 *
 * Uses Laravel's cache system to store circuit state, allowing
 * for distributed circuit breaker patterns across multiple servers.
 */
final class CircuitBreakerStore
{
    private const PREFIX = 'circuit_breaker:';

    public function __construct(
        private readonly CacheRepository $cache,
        private readonly int $timeout = 60,
    ) {}

    /**
     * Get the state for a handler.
     */
    public function getState(string $handlerClass): CircuitState
    {
        $data = $this->cache->get($this->key($handlerClass));

        if ($data === null) {
            return CircuitState::Closed;
        }

        $state = CircuitState::tryFrom($data['state'] ?? 'closed');

        // Check if open circuit has timed out (should transition to half-open)
        if ($state === CircuitState::Open && $this->hasTimedOut($data)) {
            return CircuitState::HalfOpen;
        }

        return $state ?? CircuitState::Closed;
    }

    /**
     * Set the state for a handler.
     */
    public function setState(string $handlerClass, CircuitState $state): void
    {
        $this->cache->put(
            $this->key($handlerClass),
            [
                'state' => $state->value,
                'opened_at' => $state === CircuitState::Open ? time() : null,
                'failure_count' => $state === CircuitState::Closed ? 0 : $this->getFailureCount($handlerClass),
                'half_open_attempts' => $state === CircuitState::HalfOpen ? 0 : null,
            ],
            $this->timeout * 2  // Keep data longer than timeout for analysis
        );
    }

    /**
     * Record a failure for a handler.
     */
    public function recordFailure(string $handlerClass): int
    {
        $key = $this->key($handlerClass);
        $data = $this->cache->get($key) ?? [
            'state' => CircuitState::Closed->value,
            'failure_count' => 0,
            'opened_at' => null,
            'half_open_attempts' => null,
        ];

        $data['failure_count'] = ($data['failure_count'] ?? 0) + 1;
        $data['last_failure_at'] = time();

        $this->cache->put($key, $data, $this->timeout * 2);

        return $data['failure_count'];
    }

    /**
     * Record a success for a handler.
     */
    public function recordSuccess(string $handlerClass): void
    {
        $key = $this->key($handlerClass);
        $data = $this->cache->get($key);

        if ($data === null) {
            return;
        }

        $state = CircuitState::tryFrom($data['state'] ?? 'closed');

        if ($state === CircuitState::HalfOpen) {
            $data['half_open_attempts'] = ($data['half_open_attempts'] ?? 0) + 1;
        }

        $this->cache->put($key, $data, $this->timeout * 2);
    }

    /**
     * Get the failure count for a handler.
     */
    public function getFailureCount(string $handlerClass): int
    {
        $data = $this->cache->get($this->key($handlerClass));

        return $data['failure_count'] ?? 0;
    }

    /**
     * Get the half-open success count for a handler.
     */
    public function getHalfOpenAttempts(string $handlerClass): int
    {
        $data = $this->cache->get($this->key($handlerClass));

        return $data['half_open_attempts'] ?? 0;
    }

    /**
     * Reset the circuit for a handler.
     */
    public function reset(string $handlerClass): void
    {
        $this->cache->forget($this->key($handlerClass));
    }

    /**
     * Get all stored circuit states.
     *
     * @return array<string, array{state: string, failure_count: int, opened_at: ?int}>
     */
    public function all(): array
    {
        // This is a simplified implementation - in production you might
        // want to maintain a separate index of all tracked handlers
        return [];
    }

    /**
     * Check if the circuit has timed out.
     */
    private function hasTimedOut(array $data): bool
    {
        $openedAt = $data['opened_at'] ?? null;

        if ($openedAt === null) {
            return false;
        }

        return (time() - $openedAt) >= $this->timeout;
    }

    /**
     * Get the cache key for a handler.
     */
    private function key(string $handlerClass): string
    {
        return self::PREFIX . md5($handlerClass);
    }
}
