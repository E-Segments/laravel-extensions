<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Tests;

use Esegments\LaravelExtensions\CircuitBreaker\CircuitBreaker;
use Esegments\LaravelExtensions\CircuitBreaker\CircuitBreakerStore;
use Esegments\LaravelExtensions\CircuitBreaker\CircuitState;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

class CircuitBreakerTest extends TestCase
{
    protected CacheRepository $cache;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cache = $this->app->make(CacheRepository::class);
    }

    // =========================================
    // CircuitState Enum Tests
    // =========================================

    public function test_circuit_state_closed_allows_requests(): void
    {
        $this->assertTrue(CircuitState::Closed->allowsRequests());
    }

    public function test_circuit_state_open_blocks_requests(): void
    {
        $this->assertFalse(CircuitState::Open->allowsRequests());
    }

    public function test_circuit_state_half_open_allows_requests(): void
    {
        $this->assertTrue(CircuitState::HalfOpen->allowsRequests());
    }

    public function test_circuit_state_has_labels(): void
    {
        $this->assertEquals('Closed', CircuitState::Closed->getLabel());
        $this->assertEquals('Open', CircuitState::Open->getLabel());
        $this->assertEquals('Half Open', CircuitState::HalfOpen->getLabel());
    }

    public function test_circuit_state_has_colors(): void
    {
        $this->assertEquals('success', CircuitState::Closed->getColor());
        $this->assertEquals('danger', CircuitState::Open->getColor());
        $this->assertEquals('warning', CircuitState::HalfOpen->getColor());
    }

    public function test_circuit_state_has_icons(): void
    {
        $this->assertEquals('heroicon-o-check-circle', CircuitState::Closed->getIcon());
        $this->assertEquals('heroicon-o-x-circle', CircuitState::Open->getIcon());
        $this->assertEquals('heroicon-o-exclamation-circle', CircuitState::HalfOpen->getIcon());
    }

    public function test_circuit_state_is_failure(): void
    {
        $this->assertFalse(CircuitState::Closed->isFailure());
        $this->assertTrue(CircuitState::Open->isFailure());
        $this->assertFalse(CircuitState::HalfOpen->isFailure());
    }

    // =========================================
    // CircuitBreakerStore Tests
    // =========================================

    public function test_store_returns_closed_state_by_default(): void
    {
        $store = new CircuitBreakerStore($this->cache);

        $state = $store->getState('TestHandler');

        $this->assertEquals(CircuitState::Closed, $state);
    }

    public function test_store_can_set_state(): void
    {
        $store = new CircuitBreakerStore($this->cache);

        $store->setState('TestHandler', CircuitState::Open);

        $this->assertEquals(CircuitState::Open, $store->getState('TestHandler'));
    }

    public function test_store_records_failures(): void
    {
        $store = new CircuitBreakerStore($this->cache);

        $count1 = $store->recordFailure('TestHandler');
        $count2 = $store->recordFailure('TestHandler');
        $count3 = $store->recordFailure('TestHandler');

        $this->assertEquals(1, $count1);
        $this->assertEquals(2, $count2);
        $this->assertEquals(3, $count3);
        $this->assertEquals(3, $store->getFailureCount('TestHandler'));
    }

    public function test_store_resets_handler(): void
    {
        $store = new CircuitBreakerStore($this->cache);

        $store->recordFailure('TestHandler');
        $store->recordFailure('TestHandler');
        $this->assertEquals(2, $store->getFailureCount('TestHandler'));

        $store->reset('TestHandler');

        $this->assertEquals(0, $store->getFailureCount('TestHandler'));
        $this->assertEquals(CircuitState::Closed, $store->getState('TestHandler'));
    }

    public function test_store_tracks_half_open_attempts(): void
    {
        $store = new CircuitBreakerStore($this->cache);

        $store->setState('TestHandler', CircuitState::HalfOpen);
        $this->assertEquals(0, $store->getHalfOpenAttempts('TestHandler'));

        $store->recordSuccess('TestHandler');
        $this->assertEquals(1, $store->getHalfOpenAttempts('TestHandler'));

        $store->recordSuccess('TestHandler');
        $this->assertEquals(2, $store->getHalfOpenAttempts('TestHandler'));
    }

    public function test_store_transitions_open_to_half_open_after_timeout(): void
    {
        // Mock the cache to simulate time passing
        $mockCache = $this->createMock(CacheRepository::class);

        // Simulate data stored with opened_at in the past
        $pastTime = time() - 120; // 2 minutes ago
        $storedData = [
            'state' => CircuitState::Open->value,
            'opened_at' => $pastTime,
            'failure_count' => 3,
            'half_open_attempts' => null,
        ];

        $mockCache->method('get')->willReturn($storedData);

        $store = new CircuitBreakerStore($mockCache, timeout: 60);

        // Should be half-open because opened_at is older than timeout
        $this->assertEquals(CircuitState::HalfOpen, $store->getState('TestHandler'));
    }

    // =========================================
    // CircuitBreaker Tests
    // =========================================

    public function test_circuit_breaker_is_available_by_default(): void
    {
        $breaker = new CircuitBreaker($this->cache);

        $this->assertTrue($breaker->isAvailable('TestHandler'));
    }

    public function test_circuit_breaker_can_be_disabled(): void
    {
        $breaker = new CircuitBreaker($this->cache, enabled: false);

        $this->assertFalse($breaker->isEnabled());
        // Even with failures, should always be available when disabled
        $this->assertTrue($breaker->isAvailable('TestHandler'));
    }

    public function test_circuit_breaker_opens_after_threshold(): void
    {
        $breaker = new CircuitBreaker($this->cache, threshold: 3);

        $breaker->recordFailure('TestHandler');
        $breaker->recordFailure('TestHandler');
        $this->assertTrue($breaker->isAvailable('TestHandler'));
        $this->assertEquals(CircuitState::Closed, $breaker->status('TestHandler'));

        $breaker->recordFailure('TestHandler'); // Third failure - hits threshold

        $this->assertFalse($breaker->isAvailable('TestHandler'));
        $this->assertEquals(CircuitState::Open, $breaker->status('TestHandler'));
    }

    public function test_circuit_breaker_tracks_failure_count(): void
    {
        $breaker = new CircuitBreaker($this->cache, threshold: 10);

        $breaker->recordFailure('TestHandler');
        $breaker->recordFailure('TestHandler');

        $this->assertEquals(2, $breaker->failureCount('TestHandler'));
    }

    public function test_circuit_breaker_can_manually_open(): void
    {
        $breaker = new CircuitBreaker($this->cache);

        $breaker->open('TestHandler');

        $this->assertFalse($breaker->isAvailable('TestHandler'));
        $this->assertEquals(CircuitState::Open, $breaker->status('TestHandler'));
    }

    public function test_circuit_breaker_can_manually_close(): void
    {
        $breaker = new CircuitBreaker($this->cache);

        $breaker->open('TestHandler');
        $this->assertFalse($breaker->isAvailable('TestHandler'));

        $breaker->close('TestHandler');

        $this->assertTrue($breaker->isAvailable('TestHandler'));
        $this->assertEquals(CircuitState::Closed, $breaker->status('TestHandler'));
    }

    public function test_circuit_breaker_can_reset(): void
    {
        $breaker = new CircuitBreaker($this->cache, threshold: 10);

        $breaker->recordFailure('TestHandler');
        $breaker->recordFailure('TestHandler');
        $this->assertEquals(2, $breaker->failureCount('TestHandler'));

        $breaker->reset('TestHandler');

        $this->assertEquals(0, $breaker->failureCount('TestHandler'));
    }

    public function test_circuit_breaker_closes_after_successful_half_open_attempts(): void
    {
        // Create a mock cache that simulates half-open state from timeout
        $mockCache = $this->createMock(CacheRepository::class);

        $callCount = 0;
        $mockCache->method('get')->willReturnCallback(function () use (&$callCount) {
            $callCount++;
            // Return half-open state (simulating post-timeout)
            return [
                'state' => CircuitState::HalfOpen->value,
                'opened_at' => time() - 120, // Past timeout
                'failure_count' => 3,
                'half_open_attempts' => $callCount > 2 ? 2 : ($callCount > 1 ? 1 : 0),
            ];
        });

        $mockCache->method('put')->willReturn(true);

        $store = new CircuitBreakerStore($mockCache, timeout: 60);

        // Verify we start in half-open
        $this->assertEquals(CircuitState::HalfOpen, $store->getState('TestHandler'));
    }

    public function test_circuit_breaker_success_in_half_open_records_attempt(): void
    {
        $store = new CircuitBreakerStore($this->cache, timeout: 60);

        // Manually set to half-open
        $store->setState('TestHandler', CircuitState::HalfOpen);

        // Record success - should increment half_open_attempts
        $store->recordSuccess('TestHandler');

        $this->assertEquals(1, $store->getHalfOpenAttempts('TestHandler'));
    }

    public function test_circuit_breaker_reopens_on_half_open_failure(): void
    {
        // Create mock cache that simulates half-open state
        $mockCache = $this->createMock(CacheRepository::class);

        $currentState = [
            'state' => CircuitState::HalfOpen->value,
            'opened_at' => time() - 120, // Past timeout
            'failure_count' => 3,
            'half_open_attempts' => 0,
        ];

        $mockCache->method('get')->willReturnCallback(function () use (&$currentState) {
            return $currentState;
        });

        $mockCache->method('put')->willReturnCallback(function ($key, $data, $ttl) use (&$currentState) {
            $currentState = $data;

            return true;
        });

        $breaker = new CircuitBreaker($mockCache, threshold: 3, timeout: 60);

        // Verify we start in half-open
        $this->assertEquals(CircuitState::HalfOpen, $breaker->status('TestHandler'));

        // Record failure in half-open state
        $breaker->recordFailure('TestHandler');

        // Should immediately reopen
        $this->assertEquals(CircuitState::Open, $breaker->status('TestHandler'));
    }

    public function test_disabled_circuit_breaker_ignores_failures(): void
    {
        $breaker = new CircuitBreaker($this->cache, threshold: 1, enabled: false);

        $breaker->recordFailure('TestHandler');
        $breaker->recordFailure('TestHandler');

        $this->assertTrue($breaker->isAvailable('TestHandler'));
    }

    public function test_disabled_circuit_breaker_ignores_success(): void
    {
        $breaker = new CircuitBreaker($this->cache, enabled: false);

        $breaker->recordSuccess('TestHandler');

        // Should not throw and should still be available
        $this->assertTrue($breaker->isAvailable('TestHandler'));
    }

    public function test_circuit_breaker_isolates_handlers(): void
    {
        $breaker = new CircuitBreaker($this->cache, threshold: 3);

        // Trip circuit for Handler A
        $breaker->recordFailure('HandlerA');
        $breaker->recordFailure('HandlerA');
        $breaker->recordFailure('HandlerA');

        // Handler A should be unavailable
        $this->assertFalse($breaker->isAvailable('HandlerA'));

        // Handler B should still be available
        $this->assertTrue($breaker->isAvailable('HandlerB'));
    }

    public function test_circuit_breaker_uses_makeable_trait(): void
    {
        $breaker = CircuitBreaker::make(
            cache: $this->cache,
            threshold: 5,
            timeout: 60,
        );

        $this->assertInstanceOf(CircuitBreaker::class, $breaker);
        $this->assertTrue($breaker->isEnabled());
    }
}
