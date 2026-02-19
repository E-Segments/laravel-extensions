---
layout: docs
title: Circuit Breaker
description: Prevent cascading failures with automatic handler protection
order: 21
parent: "Safety"
---

## Overview

The Circuit Breaker pattern prevents cascading failures by automatically disabling handlers that fail repeatedly. When a handler fails too many times, its circuit "opens" and the handler is temporarily skipped.

## How It Works

```
     ┌──────────────────────────────────────────────────────┐
     │                   Circuit States                      │
     │                                                       │
     │   ┌─────────┐      failures       ┌─────────┐        │
     │   │ CLOSED  │ ──────────────────▶ │  OPEN   │        │
     │   │ (normal)│                      │ (skip)  │        │
     │   └────┬────┘                      └────┬────┘        │
     │        │                                │             │
     │        │ success                        │ timeout     │
     │        │                                │             │
     │        │         ┌───────────┐          │             │
     │        └─────────│ HALF-OPEN │◀─────────┘             │
     │                  │  (test)   │                        │
     │                  └───────────┘                        │
     └──────────────────────────────────────────────────────┘
```

### States

| State | Description | Behavior |
|-------|-------------|----------|
| **Closed** | Normal operation | Handler executes normally |
| **Open** | Handler disabled | Handler is skipped |
| **Half-Open** | Testing recovery | Single request allowed to test |

## Configuration

```php
// config/extensions.php
'circuit_breaker' => [
    'enabled' => true,
    'threshold' => 5,        // Failures before opening
    'timeout' => 60,         // Seconds before half-open
    'half_open_max' => 3,    // Successes to close
    'store' => 'cache',      // State storage
],
```

## Basic Usage

The circuit breaker works automatically when enabled:

```php
// If SendEmailHandler fails 5 times, it's automatically skipped
Extensions::dispatch(new UserCreated($user));
```

## Checking Circuit Status

```php
use Esegments\LaravelExtensions\Facades\Extensions;

// Check specific handler status
$status = Extensions::circuitBreaker()->status(SendEmailHandler::class);

// CircuitState enum: Closed, Open, HalfOpen
if ($status === CircuitState::Open) {
    Log::warning('Email handler circuit is open');
}

// Get failure count
$failures = Extensions::circuitBreaker()->failureCount(SendEmailHandler::class);
```

## CLI Commands

```bash
# View circuit breaker status
php artisan extension:stats

# Output:
# Circuit Breaker Status
# ┌─────────────────────┬───────────┬──────────┐
# │ Handler             │ State     │ Failures │
# ├─────────────────────┼───────────┼──────────┤
# │ SendEmailHandler    │ Open      │ 5        │
# │ NotifySlackHandler  │ Half-Open │ 3        │
# └─────────────────────┴───────────┴──────────┘
```

## Manual Control

### Reset a Circuit

```php
Extensions::circuitBreaker()->reset(SendEmailHandler::class);
```

### Force Open

```php
Extensions::circuitBreaker()->open(SendEmailHandler::class);
```

### Disable for Handler

```php
Extensions::circuitBreaker()->exclude(SendEmailHandler::class);
```

## Storage

Circuit state is stored in cache by default:

```php
'circuit_breaker' => [
    'store' => 'cache',  // Uses Laravel cache
],
```

For multi-server deployments, use Redis:

```php
'circuit_breaker' => [
    'store' => 'redis',
],
```

## Events

The circuit breaker fires events:

```php
// Listen for circuit state changes
Event::listen(CircuitOpened::class, function ($event) {
    Log::alert("Circuit opened for {$event->handler}");
    // Notify on-call team
});

Event::listen(CircuitClosed::class, function ($event) {
    Log::info("Circuit recovered for {$event->handler}");
});
```

## Graceful Degradation

When a circuit is open, consider fallback behavior:

```php
class SendEmailHandler implements ExtensionHandlerContract
{
    public function handle(ExtensionPointContract $extension): void
    {
        try {
            // Primary: Send via SMTP
            Mail::send(...);
        } catch (Exception $e) {
            // Fallback: Queue for later
            SendEmailJob::dispatch($extension->user)->delay(now()->addMinutes(5));
            throw $e; // Re-throw to trigger circuit breaker
        }
    }
}
```

## Best Practices

### 1. Set Appropriate Thresholds

```php
// For critical handlers, higher threshold
'threshold' => 10,

// For non-critical handlers, lower threshold
'threshold' => 3,
```

### 2. Monitor Circuit Health

```php
// In a scheduled command
$breaker = Extensions::circuitBreaker();
$openCircuits = collect($breaker->getAllStatuses())
    ->filter(fn ($status) => $status === CircuitState::Open);

if ($openCircuits->isNotEmpty()) {
    // Alert operations team
}
```

### 3. Test Recovery

```php
// Periodically test half-open circuits
$schedule->command('extension:health-check')
    ->everyFiveMinutes();
```

## Disabling Circuit Breaker

For development or specific handlers:

```php
// Globally disable
'circuit_breaker' => [
    'enabled' => false,
],

// Or per-dispatch
Extensions::withoutCircuitBreaker()->dispatch($extension);
```
