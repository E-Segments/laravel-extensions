# Laravel Extensions

A type-safe, production-ready extension points system for Laravel applications.

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.2-8892BF.svg)](https://php.net/)
[![Laravel](https://img.shields.io/badge/laravel-11.x%20%7C%2012.x-FF2D20.svg)](https://laravel.com)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Documentation](https://img.shields.io/badge/docs-e--segments.github.io-blue.svg)](https://e-segments.github.io/laravel-extensions/)

## Overview

This package provides a powerful way to create extensible moments in your application using typed PHP classes instead of string-based hooks. It offers:

- **Type-safe extension points** - Full IDE support and type checking
- **Priority ordering** - Control the order handlers execute
- **Veto capability** - Handlers can interrupt processing
- **Data transformation** - Handlers can modify extension point data
- **Attribute-based registration** - Auto-discover handlers using PHP attributes
- **Graceful error handling** - Continue execution even when handlers fail
- **Circuit breaker** - Automatically disable failing handlers
- **Framework bridges** - Integrate with Eloquent, Livewire, and Filament
- **CLI tools** - Inspect, test, and manage handlers
- **Profiling** - Track handler execution performance
- **Laravel integration** - Works with Laravel's service container and events

## Installation

```bash
composer require esegments/laravel-extensions
```

The package will auto-register its service provider.

## Table of Contents

- [Quick Start](#quick-start)
- [Dispatch Methods](#dispatch-methods)
- [Interruptible Extension Points](#interruptible-extension-points)
- [Safety & Resilience](#safety--resilience)
  - [Graceful Mode](#graceful-mode)
  - [Circuit Breaker](#circuit-breaker)
  - [Mute & Silence](#mute--silence)
  - [Strict Mode](#strict-mode)
- [Advanced Registration](#advanced-registration)
  - [Conditional Registration](#conditional-registration)
  - [Scoped Handlers](#scoped-handlers)
  - [Wildcard Handlers](#wildcard-handlers)
  - [Handler Tagging](#handler-tagging)
- [Async Handlers](#async-handlers)
- [Pipelines](#pipelines)
- [Result Strategies](#result-strategies)
- [Framework Bridges](#framework-bridges)
- [CLI Commands](#cli-commands)
- [Profiling](#profiling)
- [Configuration](#configuration)

---

## Quick Start

### 1. Define an Extension Point

```php
use Esegments\LaravelExtensions\Contracts\ExtensionPointContract;

final class ValidateOrderExtension implements ExtensionPointContract
{
    public array $errors = [];

    public function __construct(
        public readonly Order $order,
        public readonly Customer $customer,
    ) {}

    public function addError(string $error): void
    {
        $this->errors[] = $error;
    }
}
```

### 2. Define a Handler

```php
use Esegments\LaravelExtensions\Contracts\ExtensionHandlerContract;
use Esegments\LaravelExtensions\Contracts\ExtensionPointContract;

final class CheckInventoryHandler implements ExtensionHandlerContract
{
    public function __construct(
        private readonly InventoryService $inventory,
    ) {}

    public function handle(ExtensionPointContract $extensionPoint): mixed
    {
        if (! $extensionPoint instanceof ValidateOrderExtension) {
            return null;
        }

        foreach ($extensionPoint->order->items as $item) {
            if (! $this->inventory->hasStock($item->product_id, $item->quantity)) {
                $extensionPoint->addError("Insufficient stock for {$item->product_id}");
            }
        }

        return null;
    }
}
```

### 3. Register Handlers

**Option A: Manual Registration**

```php
use Esegments\LaravelExtensions\HandlerRegistry;

public function boot(HandlerRegistry $registry): void
{
    $registry->register(
        ValidateOrderExtension::class,
        CheckInventoryHandler::class,
        priority: 10,
    );
}
```

**Option B: Attribute-Based Registration**

```php
use Esegments\LaravelExtensions\Attributes\ExtensionHandler;

#[ExtensionHandler(ValidateOrderExtension::class, priority: 10)]
final class CheckInventoryHandler implements ExtensionHandlerContract
{
    public function handle(ExtensionPointContract $extensionPoint): mixed
    {
        // ...
    }
}
```

### 4. Dispatch

```php
use Esegments\LaravelExtensions\Facades\Extensions;

$extension = new ValidateOrderExtension($order, $customer);
Extensions::dispatch($extension);

if (! empty($extension->errors)) {
    return response()->json(['errors' => $extension->errors], 422);
}
```

---

## Dispatch Methods

| Method | Description |
|--------|-------------|
| `dispatch($ext)` | Basic dispatch - runs all handlers, fires Laravel event |
| `dispatchInterruptible($ext)` | For InterruptibleContract - returns true/false |
| `dispatchSilent($ext)` | Dispatch WITHOUT firing Laravel event |
| `dispatchInterruptibleSilent($ext)` | Interruptible dispatch WITHOUT Laravel event |
| `dispatchWithResults($ext)` | Returns `DispatchResult` with handler results and debug info |
| `hasHandlers($class)` | Check if handlers are registered |

### DispatchResult Object

```php
$result = Extensions::dispatchWithResults($extension);

$result->extension();      // The extension point instance
$result->results();        // Collection of handler results
$result->successful();     // Collection of successful handler classes
$result->errors();         // Collection of caught exceptions
$result->skipped();        // Collection of skipped handlers with reasons
$result->debug();          // DebugInfo object (when debug enabled)
$result->wasInterrupted(); // bool
$result->interruptedBy();  // Handler class that interrupted
$result->isSuccessful();   // No errors and not interrupted
$result->hasErrors();      // bool
$result->throwOnError();   // Throws first error if any
$result->toArray();        // Array representation
```

---

## Interruptible Extension Points

For validation scenarios where handlers can veto operations:

```php
use Esegments\LaravelExtensions\Concerns\InterruptibleTrait;
use Esegments\LaravelExtensions\Contracts\InterruptibleContract;

final class BeforeOrderSubmit implements InterruptibleContract
{
    use InterruptibleTrait;

    public function __construct(public readonly Order $order) {}
}
```

Handler returns `false` to interrupt:

```php
public function handle(ExtensionPointContract $ext): mixed
{
    if ($ext->order->total > 50000) {
        return false; // Stops processing
    }
    return null;
}
```

Dispatch with interruption check:

```php
$canProceed = Extensions::dispatchInterruptible($extension);

if (! $canProceed) {
    $interruptedBy = $extension->getInterruptedBy();
    // Handle veto
}
```

---

## Safety & Resilience

### Graceful Mode

Continue execution even when handlers throw exceptions:

```php
// Enable for single dispatch
Extensions::gracefully()->dispatch($extension);

// Get errors without stopping
$result = Extensions::gracefully()->dispatchWithResults($extension);
$result->errors(); // Collection of caught exceptions
$result->successful(); // Handlers that succeeded
```

Config:
```php
// config/extensions.php
'graceful_mode' => env('EXTENSIONS_GRACEFUL', false),
```

### Circuit Breaker

Automatically disable handlers that fail repeatedly:

```php
// Config
'circuit_breaker' => [
    'enabled' => true,
    'threshold' => 5,      // failures before opening
    'timeout' => 60,       // seconds before retry
    'half_open_max' => 3,  // test requests before closing
    'store' => 'cache',    // 'cache' or 'redis'
],

// Manual control
$breaker = Extensions::circuitBreaker();
$breaker->open(MyHandler::class);   // Force open
$breaker->close(MyHandler::class);  // Force close
$breaker->status(MyHandler::class); // CircuitState enum
$breaker->isAvailable(MyHandler::class); // bool
```

### Mute & Silence

Temporarily disable specific handlers or all dispatching:

```php
// Mute specific handler
Extensions::mute(AuditHandler::class);
Extensions::unmute(AuditHandler::class);
Extensions::isMuted(AuditHandler::class);

// Scope muting
Extensions::withMuted(AuditHandler::class, function () {
    // Handler muted inside closure only
});

// Silence ALL dispatching (useful for seeding/testing)
Extensions::silence(function () {
    User::factory()->count(1000)->create();
    // No handlers execute
});

// Or globally
Extensions::silenceAll();
// ... do work ...
Extensions::resumeAll();
```

### Strict Mode

Throw exception when dispatching to extension points with no handlers:

```php
// config/extensions.php
'strict_mode' => env('EXTENSIONS_STRICT', false),

// Or per-dispatch
Extensions::strictly()->dispatch($extension);
// Throws StrictModeException if no handlers registered
```

---

## Advanced Registration

### Conditional Registration

Register handlers only when conditions are met:

```php
use Esegments\LaravelExtensions\Facades\Extensions;

// Boolean condition
Extensions::when(app()->environment('production'))
    ->register(OrderPlaced::class, ProductionAuditHandler::class);

// Closure condition (evaluated at dispatch time)
Extensions::when(fn () => Feature::active('new-billing'))
    ->register(OrderPlaced::class, NewBillingHandler::class);
```

Attribute-based:

```php
use Esegments\LaravelExtensions\Attributes\When;
use Esegments\LaravelExtensions\Attributes\WhenFeature;

#[ExtensionHandler(UserCreated::class)]
#[When('production')]
class ProductionOnlyHandler { }

#[ExtensionHandler(UserCreated::class)]
#[WhenFeature('analytics')]
class AnalyticsHandler { }
```

### Scoped Handlers

Handlers active only within specific scope:

```php
// Request scope (auto-cleanup after request)
Extensions::forRequest()
    ->register(PageViewed::class, SessionTracker::class);

// Tenant scope
Extensions::forTenant($tenant->id)
    ->register(OrderPlaced::class, TenantHandler::class);

// Custom scope
Extensions::scope('import-job-123')
    ->register(ProductCreated::class, ImportLogger::class);

// Clear scope
Extensions::clearScope('import-job-123');
```

Add middleware to auto-cleanup request scopes:

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->append(\Esegments\LaravelExtensions\Middleware\ClearRequestScopedHandlers::class);
})
```

### Wildcard Handlers

Listen to multiple extension points with patterns:

```php
// Listen to all *Created extension points
Extensions::onAny('*Created', CreationTracker::class);

// Listen to namespace pattern
Extensions::onAny('Modules\\Orders\\Extensions\\*', OrderAuditLogger::class);

// Listen to prefix pattern
Extensions::onAny('Before*', PreActionLogger::class);
```

### Handler Tagging

Tag handlers for bulk operations:

```php
// Register with tags
Extensions::registerWithTags(
    UserCreated::class,
    EmailHandler::class,
    priority: 10,
    tags: ['notifications', 'email'],
);

// Disable all handlers with tag
Extensions::disableTag('email');

// Enable all handlers with tag
Extensions::enableTag('email');

// Get handlers by tag
$handlers = Extensions::tagged('notifications');
```

---

## Async Handlers

Run handlers in background queues:

```php
use Esegments\LaravelExtensions\Contracts\AsyncHandlerContract;
use Esegments\LaravelExtensions\Attributes\Async;

#[ExtensionHandler(OrderPlaced::class)]
#[Async(
    queue: 'high-priority',
    delay: 60,
    retries: 3,
    backoff: 'exponential',  // 'fixed', 'linear', 'exponential'
    timeout: 120,
    onFailure: NotifyAdmin::class,
    onRetry: LogRetry::class,
    uniqueJob: true,
    uniqueLockTimeout: 300,
)]
class ProcessOrderAsync implements AsyncHandlerContract
{
    public function handle(ExtensionPointContract $ext): mixed
    {
        // Runs in queue
    }
}
```

### Batch Dispatch

Dispatch multiple extension points as a batch:

```php
$batch = Extensions::batch([
    new OrderPlaced($order1),
    new OrderPlaced($order2),
    new OrderPlaced($order3),
]);

$batch->name('process-orders')
    ->onQueue('high')
    ->allowFailures()
    ->dispatch();
```

---

## Pipelines

Chain handlers as a transformation pipeline:

```php
use Esegments\LaravelExtensions\Facades\Extensions;

$result = Extensions::pipeline(new ProcessOrder($order))
    ->through([
        ValidateInventory::class,
        ApplyDiscounts::class,
        CalculateTax::class,
        ProcessPayment::class,
    ])
    ->onFailure(fn ($e) => Log::error('Pipeline failed', ['error' => $e]))
    ->onSuccess(fn ($result) => Log::info('Pipeline complete'))
    ->continueOnFailure() // Don't stop on handler errors
    ->run();
```

Pipeline handlers receive passable data:

```php
class ApplyDiscounts
{
    public function handle($passable, $extensionPoint, Closure $next)
    {
        $passable['discount'] = $this->calculateDiscount($passable);

        return $next($passable);
    }
}
```

---

## Result Strategies

Different strategies for aggregating handler results:

```php
// First non-null result wins
$price = Extensions::firstResult()
    ->aggregate($results);

// Merge all results into collection
$validationErrors = Extensions::mergeResults()
    ->aggregate($results);

// Reduce results with callback
$total = Extensions::reduceResults(
    fn ($carry, $result) => $carry + $result,
    initial: 0
)->aggregate($results);

// Built-in reducers
ReduceResultsStrategy::sum();      // Sum numeric results
ReduceResultsStrategy::concat();   // Concatenate strings
ReduceResultsStrategy::allTrue();  // Logical AND
ReduceResultsStrategy::anyTrue();  // Logical OR
ReduceResultsStrategy::count();    // Count results
ReduceResultsStrategy::min();      // Minimum value
ReduceResultsStrategy::max();      // Maximum value
```

---

## Framework Bridges

### Eloquent Bridge

Auto-dispatch extension points on model events:

```php
use Esegments\LaravelExtensions\Bridges\Eloquent\HasExtensionPoints;

class User extends Model
{
    use HasExtensionPoints;

    // Auto-generates extension points:
    // UserCreating, UserCreated, UserUpdating, UserUpdated, UserDeleting, UserDeleted

    // Or customize
    protected array $extensionPoints = [
        'created' => UserRegistered::class,  // Custom class
        'updated' => true,                    // Auto-generated
        'deleted' => false,                   // Disabled
    ];
}
```

Enable in config:
```php
'bridges' => [
    'eloquent' => true,
],
```

### Livewire Bridge

Extension points for Livewire component lifecycle:

```php
use Esegments\LaravelExtensions\Bridges\Livewire\HasLivewireExtensions;

class CreatePost extends Component
{
    use HasLivewireExtensions;

    // Dispatches: CreatePostMounting, CreatePostRendering, CreatePostUpdated
}
```

### Filament Bridge

Extension points for Filament resource actions:

```php
use Esegments\LaravelExtensions\Bridges\Filament\HasFilamentExtensions;

class UserResource extends Resource
{
    use HasFilamentExtensions;

    // Dispatches: UserBeforeCreate, UserAfterCreate, UserBeforeSave, etc.
}
```

---

## CLI Commands

| Command | Description |
|---------|-------------|
| `extension:list` | List all extension points and handlers |
| `extension:inspect {point}` | Deep dive into specific extension point |
| `extension:test {point}` | Test handler execution |
| `extension:cache` | Cache discovered handlers for production |
| `extension:clear` | Clear handler cache |
| `extension:stats` | Show execution statistics |
| `extension:ide-helper` | Generate IDE helper file |

Examples:

```bash
# List all extension points
php artisan extension:list

# Filter by extension point
php artisan extension:list --point=UserCreated

# Filter by handler
php artisan extension:list --handler=AuditHandler

# Show unused extension points
php artisan extension:list --unused

# Inspect specific extension point
php artisan extension:inspect UserCreated

# Test with data
php artisan extension:test UserCreated --with-data='{"user_id": 1}'

# Dry run
php artisan extension:test UserCreated --dry-run

# Cache for production
php artisan extension:cache

# View stats
php artisan extension:stats --point=UserCreated --period=24h
```

---

## Profiling

Track handler execution performance:

```php
// config/extensions.php
'profiling' => [
    'enabled' => env('EXTENSIONS_PROFILING', false),
    'slow_threshold' => 100, // ms - log warning when exceeded
    'log_channel' => 'extensions',
],
```

Access profile data:

```php
$result = Extensions::dispatchWithResults($extension);

$result->totalTime();           // Total execution time in ms
$result->debug()?->handlers;    // Per-handler timing
```

### Debugbar Integration

When Laravel Debugbar is installed, extension dispatches are automatically tracked.

### Pulse Integration

When Laravel Pulse is installed, extension metrics are recorded for monitoring.

---

## Configuration

Publish config:

```bash
php artisan vendor:publish --tag=extensions-config
```

Full configuration:

```php
// config/extensions.php
return [
    // Debug mode - logs all dispatches
    'debug' => env('EXTENSIONS_DEBUG', false),
    'log_channel' => env('EXTENSIONS_LOG_CHANNEL'),

    // Graceful mode - continue on handler errors
    'graceful_mode' => env('EXTENSIONS_GRACEFUL', false),

    // Strict mode - throw on unhandled extension points
    'strict_mode' => env('EXTENSIONS_STRICT', false),

    // Circuit breaker
    'circuit_breaker' => [
        'enabled' => env('EXTENSIONS_CIRCUIT_BREAKER', true),
        'threshold' => 5,
        'timeout' => 60,
        'half_open_max' => 3,
        'store' => 'cache',
    ],

    // Auto-discovery
    'discovery' => [
        'enabled' => env('EXTENSIONS_DISCOVERY_ENABLED', false),
        'directories' => [
            'app/Handlers',
            'app/Extensions/Handlers',
        ],
        'cache' => env('EXTENSIONS_DISCOVERY_CACHE', true),
        'cache_key' => 'extensions.discovered_handlers',
    ],

    // Async handlers
    'async' => [
        'default_queue' => env('EXTENSIONS_ASYNC_QUEUE', 'default'),
        'tries' => env('EXTENSIONS_ASYNC_TRIES', 3),
        'backoff' => env('EXTENSIONS_ASYNC_BACKOFF', 10),
        'backoff_strategy' => 'exponential',
    ],

    // Profiling
    'profiling' => [
        'enabled' => env('EXTENSIONS_PROFILING', false),
        'slow_threshold' => 100,
        'log_channel' => 'extensions',
    ],

    // Third-party integrations
    'integrations' => [
        'debugbar' => env('EXTENSIONS_DEBUGBAR', true),
        'pulse' => env('EXTENSIONS_PULSE', true),
    ],

    // Framework bridges
    'bridges' => [
        'eloquent' => false,
        'livewire' => false,
        'filament' => false,
    ],

    // Handler caching
    'cache' => [
        'enabled' => env('EXTENSIONS_CACHE', false),
        'key' => 'extensions:handlers',
        'ttl' => 86400,
    ],
];
```

---

## Priority System

Handlers run in priority order (lower numbers first):

| Range | Purpose |
|-------|---------|
| 0-49 | Critical (veto checks, security) |
| 50-99 | High (cache invalidation) |
| 100-149 | Normal (default: 100) |
| 150-199 | Low (notifications) |
| 200+ | Very low (analytics, async) |

---

## Testing

```bash
cd packages/esegments/laravel-extensions
./vendor/bin/phpunit
```

---

## Documentation

📖 **Full documentation available at: [e-segments.github.io/laravel-extensions](https://e-segments.github.io/laravel-extensions/)**

---

## License

MIT
