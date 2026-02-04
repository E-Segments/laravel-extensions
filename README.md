# Laravel Extensions

A minimal, type-safe extension points system for Laravel applications.

**NOT WordPress-style** (no global `addAction`/`applyFilters` with string names).
**NOT discovery-based** - Manual registration in service providers for explicit, predictable behavior.

## Installation

```bash
composer require esegments/laravel-extensions
```

The package auto-registers its service provider.

## Quick Start

### 1. Define an Extension Point

```php
use Esegments\LaravelExtensions\Contracts\ExtensionPointContract;
use Esegments\LaravelExtensions\Contracts\InterruptibleContract;
use Esegments\LaravelExtensions\Concerns\InterruptibleTrait;

final class ValidateOrderExtension implements InterruptibleContract
{
    use InterruptibleTrait;

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

        // Return false to interrupt/veto
        return ! empty($extensionPoint->errors) ? false : null;
    }
}
```

### 3. Register Handlers (in Service Provider)

```php
use Esegments\LaravelExtensions\Facades\Extensions;

public function boot(): void
{
    // Using handler class
    Extensions::register(
        ValidateOrderExtension::class,
        CheckInventoryHandler::class,
        priority: 10,  // Lower = runs first
    );

    // Using closure
    Extensions::register(
        ValidateOrderExtension::class,
        fn (ValidateOrderExtension $ext) => $ext->order->total > 10000
            ? $ext->addError('Orders over $10k require approval')
            : null,
        priority: 20,
    );
}
```

### 4. Dispatch Extension Point

```php
use Esegments\LaravelExtensions\Facades\Extensions;

$extension = new ValidateOrderExtension($order, $customer);
$canProceed = Extensions::dispatchInterruptible($extension);

if (! $canProceed || ! empty($extension->errors)) {
    return response()->json([
        'success' => false,
        'errors' => $extension->errors,
        'interrupted_by' => $extension->getInterruptedBy(),
    ], 422);
}

// Proceed with order...
```

## Contracts

### ExtensionPointContract

Marker interface for extension points. Extension points are typed PHP classes that define specific points in your application where handlers can be registered.

### InterruptibleContract

Extension points that can be vetoed/interrupted. When a handler returns `false`, no further handlers execute.

```php
final class BeforeDeleteExtension implements InterruptibleContract
{
    use InterruptibleTrait;

    public function __construct(
        public readonly Model $model,
    ) {}
}

// Handler can veto deletion
Extensions::register(BeforeDeleteExtension::class, function ($ext) {
    if ($ext->model->is_protected) {
        return false; // Veto!
    }
});
```

### PipeableContract

Extension points that allow data transformation. Handlers modify the extension point's data in sequence.

```php
final class CalculatePriceExtension implements PipeableContract
{
    public function __construct(
        public readonly Product $product,
        public float $price,
    ) {}
}

// Handler 1: Apply discount
Extensions::register(CalculatePriceExtension::class, function ($ext) {
    $ext->price *= 0.9;
}, priority: 10);

// Handler 2: Apply tax
Extensions::register(CalculatePriceExtension::class, function ($ext) {
    $ext->price *= 1.1;
}, priority: 20);
```

### ExtensionHandlerContract

Contract for handler classes that process extension points.

```php
final class MyHandler implements ExtensionHandlerContract
{
    public function handle(ExtensionPointContract $extensionPoint): mixed
    {
        // Handle the extension point
        return null; // or false to interrupt
    }
}
```

## Priority System

| Range | Purpose |
|-------|---------|
| 0-49 | Critical (veto checks, security) |
| 50-99 | High (cache invalidation) |
| 100-149 | Normal (default: 100) |
| 150-199 | Low (notifications) |
| 200+ | Very low (analytics) |

Lower values run first.

## Dispatcher Methods

```php
// Dispatch and get the extension point back
$ext = Extensions::dispatch($extensionPoint);

// Dispatch interruptible and get boolean
$canProceed = Extensions::dispatchInterruptible($extensionPoint);

// Dispatch without triggering Laravel events
$ext = Extensions::dispatchSilent($extensionPoint);

// Check if handlers exist
$hasHandlers = Extensions::hasHandlers(MyExtension::class);
```

## Laravel Event Integration

Extension points are also dispatched as Laravel events:

```php
// In EventServiceProvider
protected $listen = [
    ValidateOrderExtension::class => [
        LogOrderValidation::class,
    ],
];
```

Disable in config:

```php
// config/extensions.php
'dispatch_as_events' => false,
```

## Registry Access

```php
use Esegments\LaravelExtensions\Facades\Extensions;

// Get the registry
$registry = Extensions::registry();

// Register directly
$registry->register(MyExtension::class, MyHandler::class);

// Check handlers
$registry->hasHandlers(MyExtension::class);
$registry->countHandlers(MyExtension::class);

// Get handlers with priorities
$registry->getHandlersWithPriorities(MyExtension::class);

// Clear handlers
$registry->forget(MyExtension::class);
$registry->clear();
```

## Octane Support

The package is Octane-safe. Handlers are registered in service providers which are only executed once per worker.

If you need to clear handlers between requests (unusual), enable in config:

```php
// config/extensions.php
'clear_on_octane_terminate' => true,
```

## Testing

```php
use Esegments\LaravelExtensions\Facades\Extensions;

public function test_order_validation(): void
{
    // Clear any existing handlers
    Extensions::registry()->clear();

    // Register test handler
    Extensions::register(
        ValidateOrderExtension::class,
        fn ($ext) => $ext->addError('Test error'),
    );

    $ext = new ValidateOrderExtension($order, $customer);
    Extensions::dispatch($ext);

    $this->assertEquals(['Test error'], $ext->errors);
}
```

## Comparison

| Aspect | WordPress Hooks | Laravel Extensions |
|--------|-----------------|-------------------|
| Type Safety | String names | Typed PHP classes |
| IDE Support | None | Full auto-complete |
| Registration | Global functions | Service provider |
| Testability | Global state | Container-based |
| Veto | Complex | Return `false` |
| Octane | Static arrays | Container singleton |
| Complexity | High | Minimal |

## License

MIT
