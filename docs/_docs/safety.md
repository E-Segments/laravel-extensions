---
title: "Safety Features"
description: "Circuit breakers, graceful execution, and error handling"
order: 22
parent: "Safety"
---

Laravel Extensions provides multiple safety mechanisms to ensure your application remains stable even when handlers fail.

## Circuit Breaker

The Circuit Breaker pattern prevents cascading failures by stopping calls to failing handlers.

### How It Works

```
CLOSED (normal) → failures exceed threshold → OPEN (blocking)
                                                    ↓
                                              timeout expires
                                                    ↓
                                             HALF_OPEN (testing)
                                                    ↓
                    success → CLOSED          failure → OPEN
```

### Configuration

```php
// config/extensions.php
'circuit_breaker' => [
    'enabled' => true,
    'threshold' => 5,        // Failures before opening
    'timeout' => 60,         // Seconds before trying again
    'store' => 'cache',      // 'cache' or 'array'
],
```

### Usage

Circuit breakers are automatic when enabled. You can also use them manually:

```php
use Esegments\LaravelExtensions\CircuitBreaker\CircuitBreaker;

$breaker = new CircuitBreaker('external-api');

$result = $breaker->call(function () {
    return Http::get('https://api.example.com/data');
});

// Check state
$breaker->isOpen();      // true if blocking calls
$breaker->isClosed();    // true if allowing calls
$breaker->isHalfOpen();  // true if testing recovery
```

### Circuit States

```php
use Esegments\LaravelExtensions\CircuitBreaker\CircuitState;

CircuitState::Closed;   // Normal operation
CircuitState::Open;     // Blocking calls
CircuitState::HalfOpen; // Testing recovery
```

## Graceful Execution

The `GracefulExecution` trait allows handlers to fail without crashing:

```php
use Esegments\LaravelExtensions\Concerns\GracefulExecution;

class MyHandler
{
    use GracefulExecution;

    public function handle($data)
    {
        return $this->gracefully(function () use ($data) {
            // Risky operation
            return $this->processData($data);
        }, default: null);
    }
}
```

### With Logging

```php
$result = $this->gracefully(
    fn() => $this->riskyOperation(),
    default: [],
    log: true,
    level: 'warning'
);
```

### With Callback on Failure

```php
$result = $this->gracefully(
    fn() => $this->riskyOperation(),
    onError: function (\Throwable $e) {
        Report::exception($e);
        return ['error' => $e->getMessage()];
    }
);
```

## Mutable Trait

The `Mutable` trait allows temporarily muting exceptions:

```php
use Esegments\LaravelExtensions\Concerns\Mutable;

class DataImporter
{
    use Mutable;

    public function import(array $rows)
    {
        $results = [];

        foreach ($rows as $row) {
            // Mute exceptions for individual rows
            $result = $this->muted(fn() => $this->processRow($row));
            if ($result !== null) {
                $results[] = $result;
            }
        }

        return $results;
    }
}
```

### Global Mute

```php
$importer->mute(); // Enable muting

$importer->import($data); // No exceptions thrown

$importer->unmute(); // Disable muting
```

## Silenceable Trait

The `Silenceable` trait suppresses output:

```php
use Esegments\LaravelExtensions\Concerns\Silenceable;

class VerboseProcessor
{
    use Silenceable;

    public function process()
    {
        return $this->silenced(function () {
            // Any echo/print statements are captured
            echo "Processing...";
            return $this->doWork();
        });
    }
}
```

## Dispatch Result

All dispatches return a `DispatchResult` object:

```php
$result = Extensions::dispatch('some.event', $data);

// Check status
$result->successful();  // All handlers succeeded
$result->failed();      // Any handler failed
$result->partial();     // Some succeeded, some failed

// Get results
$result->all();         // All results
$result->successes();   // Only successful results
$result->failures();    // Only failures

// Get first result
$result->first();       // First result (any)
$result->firstSuccess(); // First successful
$result->firstFailure(); // First failure

// Count
$result->count();
$result->successCount();
$result->failureCount();
```

## Error Handling Strategies

### Fail Fast

Stop on first error (default):

```php
Extensions::dispatch('critical.operation', $data)
    ->failFast();
```

### Collect All

Continue despite errors:

```php
Extensions::dispatch('batch.process', $items)
    ->collectAll()
    ->failures(); // Get all errors
```

### With Fallback

```php
$result = Extensions::dispatch('get.data', $key)
    ->withFallback(fn() => Cache::get($key))
    ->first();
```

## Combining Safety Features

```php
use Esegments\LaravelExtensions\Concerns\GracefulExecution;
use Esegments\LaravelExtensions\Concerns\Mutable;

class RobustHandler
{
    use GracefulExecution, Mutable;

    public function handle($data)
    {
        // Mute for non-critical operations
        $this->muted(fn() => $this->logActivity($data));

        // Graceful for main operation with fallback
        return $this->gracefully(
            fn() => $this->processData($data),
            default: $this->getCachedResult($data)
        );
    }
}
```
