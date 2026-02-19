---
layout: docs
title: Configuration
description: Complete configuration reference
order: 3
---

## Publishing Config

```bash
php artisan vendor:publish --tag=extensions-config
```

## Full Configuration

```php
// config/extensions.php

return [
    /*
    |--------------------------------------------------------------------------
    | Debug Mode
    |--------------------------------------------------------------------------
    |
    | Enable verbose logging for troubleshooting.
    |
    */
    'debug' => env('EXTENSIONS_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Log Channel
    |--------------------------------------------------------------------------
    |
    | The log channel for extension-related messages.
    |
    */
    'log_channel' => env('EXTENSIONS_LOG_CHANNEL', null),

    /*
    |--------------------------------------------------------------------------
    | Graceful Mode
    |--------------------------------------------------------------------------
    |
    | When enabled, handler exceptions are caught and collected
    | instead of being thrown.
    |
    */
    'graceful_mode' => env('EXTENSIONS_GRACEFUL', false),

    /*
    |--------------------------------------------------------------------------
    | Strict Mode
    |--------------------------------------------------------------------------
    |
    | When enabled, throws exception if no handlers are registered
    | for a dispatched extension point.
    |
    */
    'strict_mode' => env('EXTENSIONS_STRICT', false),

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker
    |--------------------------------------------------------------------------
    |
    | Automatically disable failing handlers to prevent cascading failures.
    |
    */
    'circuit_breaker' => [
        'enabled' => env('EXTENSIONS_CIRCUIT_BREAKER', true),
        'threshold' => 5,        // Failures before opening
        'timeout' => 60,         // Seconds before testing recovery
        'half_open_max' => 3,    // Successes needed to close
        'store' => 'cache',      // State storage driver
    ],

    /*
    |--------------------------------------------------------------------------
    | Attribute Discovery
    |--------------------------------------------------------------------------
    |
    | Auto-discover handlers using PHP attributes.
    |
    */
    'discovery' => [
        'enabled' => env('EXTENSIONS_DISCOVERY', false),
        'directories' => [
            'app/Handlers',
            'app/Extensions/Handlers',
        ],
        'cache' => env('EXTENSIONS_DISCOVERY_CACHE', true),
        'cache_key' => 'extensions.discovered_handlers',
    ],

    /*
    |--------------------------------------------------------------------------
    | Async Processing
    |--------------------------------------------------------------------------
    |
    | Default settings for async handlers.
    |
    */
    'async' => [
        'default_queue' => env('EXTENSIONS_ASYNC_QUEUE', 'default'),
        'tries' => 3,
        'backoff' => 10,
        'backoff_strategy' => 'exponential', // 'linear', 'exponential', 'fixed'
    ],

    /*
    |--------------------------------------------------------------------------
    | Profiling
    |--------------------------------------------------------------------------
    |
    | Track handler execution performance.
    |
    */
    'profiling' => [
        'enabled' => env('EXTENSIONS_PROFILING', false),
        'slow_threshold' => 100, // milliseconds
        'log_channel' => 'extensions',
    ],

    /*
    |--------------------------------------------------------------------------
    | Integrations
    |--------------------------------------------------------------------------
    */
    'integrations' => [
        'debugbar' => env('EXTENSIONS_DEBUGBAR', true),
        'pulse' => env('EXTENSIONS_PULSE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Framework Bridges
    |--------------------------------------------------------------------------
    |
    | Auto-dispatch extension points on framework events.
    |
    */
    'bridges' => [
        'eloquent' => env('EXTENSIONS_ELOQUENT_BRIDGE', false),
        'livewire' => env('EXTENSIONS_LIVEWIRE_BRIDGE', false),
        'filament' => env('EXTENSIONS_FILAMENT_BRIDGE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Handler Cache
    |--------------------------------------------------------------------------
    |
    | Cache registered handlers for performance.
    |
    */
    'cache' => [
        'enabled' => env('EXTENSIONS_CACHE', false),
        'key' => 'extensions:handlers',
        'ttl' => 86400, // 24 hours
    ],
];
```

## Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `EXTENSIONS_DEBUG` | `false` | Enable debug logging |
| `EXTENSIONS_LOG_CHANNEL` | `null` | Log channel |
| `EXTENSIONS_GRACEFUL` | `false` | Catch handler errors |
| `EXTENSIONS_STRICT` | `false` | Error on no handlers |
| `EXTENSIONS_CIRCUIT_BREAKER` | `true` | Enable circuit breaker |
| `EXTENSIONS_DISCOVERY` | `false` | Enable attribute discovery |
| `EXTENSIONS_DISCOVERY_CACHE` | `true` | Cache discovered handlers |
| `EXTENSIONS_ASYNC_QUEUE` | `default` | Default async queue |
| `EXTENSIONS_PROFILING` | `false` | Enable profiling |
| `EXTENSIONS_DEBUGBAR` | `true` | Debugbar integration |
| `EXTENSIONS_PULSE` | `true` | Pulse integration |
| `EXTENSIONS_CACHE` | `false` | Cache handlers |

## Production Configuration

Recommended settings for production:

```env
EXTENSIONS_DEBUG=false
EXTENSIONS_GRACEFUL=true
EXTENSIONS_CIRCUIT_BREAKER=true
EXTENSIONS_DISCOVERY=true
EXTENSIONS_DISCOVERY_CACHE=true
EXTENSIONS_CACHE=true
EXTENSIONS_PROFILING=false
```

Cache handlers and discovery:

```bash
php artisan extension:cache
```

## Development Configuration

Recommended settings for development:

```env
EXTENSIONS_DEBUG=true
EXTENSIONS_GRACEFUL=false
EXTENSIONS_CIRCUIT_BREAKER=false
EXTENSIONS_DISCOVERY=true
EXTENSIONS_DISCOVERY_CACHE=false
EXTENSIONS_PROFILING=true
```
