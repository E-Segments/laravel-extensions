<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Debug Mode
    |--------------------------------------------------------------------------
    |
    | When enabled, the extension dispatcher will log all dispatched extension
    | points and their handlers. This is useful during development but should
    | be disabled in production for performance.
    |
    */
    'debug' => env('EXTENSIONS_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Log Channel
    |--------------------------------------------------------------------------
    |
    | The log channel to use for extension point logging when debug mode is
    | enabled. Set to null to use the default log channel.
    |
    */
    'log_channel' => env('EXTENSIONS_LOG_CHANNEL'),

    /*
    |--------------------------------------------------------------------------
    | Graceful Mode
    |--------------------------------------------------------------------------
    |
    | When enabled, handler errors are caught and collected rather than thrown,
    | allowing all handlers to execute even if some fail. Use dispatchWithResults()
    | to get detailed error information.
    |
    */
    'graceful_mode' => env('EXTENSIONS_GRACEFUL', false),

    /*
    |--------------------------------------------------------------------------
    | Strict Mode
    |--------------------------------------------------------------------------
    |
    | When enabled, dispatching to an extension point with no registered handlers
    | will throw a StrictModeException. This helps catch configuration errors
    | during development.
    |
    */
    'strict_mode' => env('EXTENSIONS_STRICT', false),

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker
    |--------------------------------------------------------------------------
    |
    | Configure the circuit breaker pattern to prevent cascading failures by
    | temporarily disabling handlers that are experiencing repeated failures.
    |
    */
    'circuit_breaker' => [
        /*
        | Enable or disable the circuit breaker.
        */
        'enabled' => env('EXTENSIONS_CIRCUIT_BREAKER', true),

        /*
        | Number of failures before the circuit opens.
        */
        'threshold' => 5,

        /*
        | Seconds before a half-open retry is allowed.
        */
        'timeout' => 60,

        /*
        | Number of successful requests in half-open state before closing.
        */
        'half_open_max' => 3,

        /*
        | Storage driver for circuit state: 'cache' or 'redis'.
        */
        'store' => 'cache',
    ],

    /*
    |--------------------------------------------------------------------------
    | Handler Discovery
    |--------------------------------------------------------------------------
    |
    | Configure automatic handler discovery using PHP attributes.
    | When enabled, the package will scan the configured directories for
    | classes with the #[ExtensionHandler] attribute.
    |
    */
    'discovery' => [
        'enabled' => env('EXTENSIONS_DISCOVERY_ENABLED', false),

        /*
        | Directories to scan for handler classes.
        | Paths are relative to the application base path.
        */
        'directories' => [
            'app/Handlers',
            'app/Extensions/Handlers',
        ],

        /*
        | Whether to cache discovered handlers.
        | Recommended for production.
        */
        'cache' => env('EXTENSIONS_DISCOVERY_CACHE', true),

        /*
        | Cache key for discovered handlers.
        */
        'cache_key' => 'extensions.discovered_handlers',
    ],

    /*
    |--------------------------------------------------------------------------
    | Async Handlers
    |--------------------------------------------------------------------------
    |
    | Configure async handler behavior.
    |
    */
    'async' => [
        /*
        | Default queue for async handlers when not specified.
        */
        'default_queue' => env('EXTENSIONS_ASYNC_QUEUE', 'default'),

        /*
        | Default number of retry attempts for async handlers.
        */
        'tries' => env('EXTENSIONS_ASYNC_TRIES', 3),

        /*
        | Default backoff in seconds between retries.
        */
        'backoff' => env('EXTENSIONS_ASYNC_BACKOFF', 10),

        /*
        | Backoff strategy: 'linear' or 'exponential'.
        */
        'backoff_strategy' => 'exponential',
    ],

    /*
    |--------------------------------------------------------------------------
    | Profiling
    |--------------------------------------------------------------------------
    |
    | Configure execution profiling for handlers.
    |
    */
    'profiling' => [
        /*
        | Enable execution profiling.
        */
        'enabled' => env('EXTENSIONS_PROFILING', false),

        /*
        | Threshold in milliseconds for slow handler warnings.
        */
        'slow_threshold' => 100,

        /*
        | Log channel for profiling output.
        */
        'log_channel' => 'extensions',
    ],

    /*
    |--------------------------------------------------------------------------
    | Integrations
    |--------------------------------------------------------------------------
    |
    | Configure third-party integrations.
    |
    */
    'integrations' => [
        /*
        | Enable Laravel Debugbar integration (if installed).
        */
        'debugbar' => env('EXTENSIONS_DEBUGBAR', true),

        /*
        | Enable Laravel Pulse integration (if installed).
        */
        'pulse' => env('EXTENSIONS_PULSE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Framework Bridges
    |--------------------------------------------------------------------------
    |
    | Configure framework bridges for automatic extension point dispatching.
    |
    */
    'bridges' => [
        /*
        | Enable Eloquent model event bridge.
        */
        'eloquent' => false,

        /*
        | Enable Livewire component lifecycle bridge.
        */
        'livewire' => false,

        /*
        | Enable Filament admin action bridge.
        */
        'filament' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Configure handler discovery caching.
    |
    */
    'cache' => [
        /*
        | Enable handler caching for production.
        */
        'enabled' => env('EXTENSIONS_CACHE', false),

        /*
        | Cache key for handlers.
        */
        'key' => 'extensions:handlers',

        /*
        | Cache TTL in seconds (86400 = 24 hours).
        */
        'ttl' => 86400,
    ],
];
