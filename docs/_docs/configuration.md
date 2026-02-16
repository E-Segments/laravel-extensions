---
title: "Configuration"
description: "All configuration options explained"
order: 5
---

After publishing the configuration file, you'll find it at `config/extensions.php`.

## Full Configuration Reference

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Handler Discovery
    |--------------------------------------------------------------------------
    |
    | Configure automatic discovery of extension handlers using PHP attributes.
    |
    */
    'discovery' => [
        'enabled' => true,
        'paths' => [
            app_path('Extensions'),
            app_path('Handlers'),
        ],
        'cache' => [
            'enabled' => env('EXTENSIONS_CACHE_DISCOVERY', true),
            'key' => 'extensions.discovered_handlers',
            'ttl' => 3600,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker
    |--------------------------------------------------------------------------
    |
    | Configure the circuit breaker for fault tolerance.
    |
    */
    'circuit_breaker' => [
        'enabled' => true,
        'threshold' => 5,        // Failures before opening
        'timeout' => 60,         // Seconds before trying again
        'store' => 'cache',      // 'cache' or 'array'
    ],

    /*
    |--------------------------------------------------------------------------
    | Async Processing
    |--------------------------------------------------------------------------
    |
    | Configure default settings for async handler execution.
    |
    */
    'async' => [
        'default_queue' => 'extensions',
        'default_connection' => null,
        'retry_after' => 90,
        'max_tries' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Profiling
    |--------------------------------------------------------------------------
    |
    | Enable profiling to monitor handler execution times.
    |
    */
    'profiling' => [
        'enabled' => env('EXTENSIONS_PROFILING', false),
        'slow_threshold' => 100,  // ms - log handlers slower than this
        'store' => 'array',       // 'array', 'cache', or 'database'
    ],

    /*
    |--------------------------------------------------------------------------
    | Strict Mode
    |--------------------------------------------------------------------------
    |
    | When enabled, throws exceptions for unregistered extension points.
    |
    */
    'strict_mode' => env('EXTENSIONS_STRICT_MODE', false),

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Configure logging for extension dispatches.
    |
    */
    'logging' => [
        'enabled' => env('EXTENSIONS_LOGGING', false),
        'channel' => 'extensions',
        'level' => 'debug',
    ],

    /*
    |--------------------------------------------------------------------------
    | Signature Validation
    |--------------------------------------------------------------------------
    |
    | Validate handler signatures match expected parameters.
    |
    */
    'signature_validation' => [
        'enabled' => env('EXTENSIONS_VALIDATE_SIGNATURES', true),
        'strict' => false,  // Throw on mismatch vs just log
    ],

    /*
    |--------------------------------------------------------------------------
    | Integrations
    |--------------------------------------------------------------------------
    |
    | Configure integrations with other packages.
    |
    */
    'integrations' => [
        'debugbar' => [
            'enabled' => true,
            'collector' => true,
        ],
        'pulse' => [
            'enabled' => true,
            'sample_rate' => 1.0,
        ],
    ],
];
```

## Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `EXTENSIONS_CACHE_DISCOVERY` | `true` | Cache discovered handlers |
| `EXTENSIONS_PROFILING` | `false` | Enable execution profiling |
| `EXTENSIONS_STRICT_MODE` | `false` | Throw on unregistered points |
| `EXTENSIONS_LOGGING` | `false` | Log all dispatches |
| `EXTENSIONS_VALIDATE_SIGNATURES` | `true` | Validate handler signatures |

## Discovery Paths

Add paths where handlers should be discovered:

```php
'discovery' => [
    'paths' => [
        app_path('Extensions'),
        app_path('Handlers'),
        base_path('modules/*/Handlers'),  // Glob patterns supported
    ],
],
```

## Circuit Breaker Stores

### Cache Store (Default)

Uses Laravel's cache for circuit state. Shared across processes:

```php
'circuit_breaker' => [
    'store' => 'cache',
],
```

### Array Store

In-memory only. Useful for testing:

```php
'circuit_breaker' => [
    'store' => 'array',
],
```

## Profiling Storage

### Array Store (Default)

In-memory, cleared each request:

```php
'profiling' => [
    'store' => 'array',
],
```

### Cache Store

Persisted across requests:

```php
'profiling' => [
    'store' => 'cache',
],
```

## Custom Logging Channel

Add a custom channel in `config/logging.php`:

```php
'channels' => [
    'extensions' => [
        'driver' => 'daily',
        'path' => storage_path('logs/extensions.log'),
        'level' => 'debug',
        'days' => 14,
    ],
],
```

## Runtime Configuration

Override configuration at runtime:

```php
use Esegments\LaravelExtensions\Facades\Extensions;

// Temporarily enable profiling
Extensions::enableProfiling();

Extensions::dispatch('heavy.operation', $data);

$profile = Extensions::getProfile('heavy.operation');

Extensions::disableProfiling();
```

### Circuit Breaker Runtime Control

```php
use Esegments\LaravelExtensions\CircuitBreaker\CircuitBreaker;

// Manually open a circuit
$breaker = new CircuitBreaker('failing-service');
$breaker->open();

// Reset a circuit
$breaker->reset();
```

## Testing Configuration

In your test setup:

```php
// tests/TestCase.php
protected function setUp(): void
{
    parent::setUp();

    // Disable discovery caching
    config(['extensions.discovery.cache.enabled' => false]);

    // Use array store for circuit breaker
    config(['extensions.circuit_breaker.store' => 'array']);

    // Enable strict mode to catch missing handlers
    config(['extensions.strict_mode' => true]);
}
```

## Publishing Configuration

```bash
php artisan vendor:publish --provider="Esegments\LaravelExtensions\ExtensionServiceProvider"
```

Or publish only config:

```bash
php artisan vendor:publish --tag=extensions-config
```
