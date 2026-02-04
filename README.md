# Laravel Extensions

A minimal, type-safe extension points system for Laravel applications.

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.2-8892BF.svg)](https://php.net/)
[![Laravel](https://img.shields.io/badge/laravel-11.x%20%7C%2012.x-FF2D20.svg)](https://laravel.com)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

---

## Why This Package?

Ever wanted to let other parts of your application (or other packages) hook into specific moments? Like WordPress hooks, but **type-safe** and **Laravel-native**?

This package gives you:

- **Type-safe hooks** - Use PHP classes instead of magic strings
- **IDE friendly** - Full autocomplete and refactoring support
- **Priority control** - Define execution order for handlers
- **Veto capability** - Let handlers cancel operations
- **Zero magic** - Explicit registration, predictable behavior

---

## Installation

```bash
composer require esegments/laravel-extensions
```

The package auto-registers itself. No additional setup needed!

---

## Quick Start

### 1. Create an Extension Point

Think of an extension point as a "hook" - a moment in your code where others can plug in.

```php
use Esegments\LaravelExtensions\Contracts\InterruptibleContract;
use Esegments\LaravelExtensions\Concerns\InterruptibleTrait;

class BeforeOrderPlaced implements InterruptibleContract
{
    use InterruptibleTrait;

    public array $warnings = [];

    public function __construct(
        public readonly Order $order,
        public readonly Customer $customer,
    ) {}

    public function addWarning(string $message): void
    {
        $this->warnings[] = $message;
    }
}
```

### 2. Create a Handler

Handlers respond to extension points. They can validate, modify data, or perform side effects.

```php
use Esegments\LaravelExtensions\Contracts\ExtensionHandlerContract;
use Esegments\LaravelExtensions\Contracts\ExtensionPointContract;

class CheckInventoryHandler implements ExtensionHandlerContract
{
    public function __construct(
        private readonly InventoryService $inventory,
    ) {}

    public function handle(ExtensionPointContract $point): mixed
    {
        if (! $point instanceof BeforeOrderPlaced) {
            return null;
        }

        foreach ($point->order->items as $item) {
            if (! $this->inventory->inStock($item->product_id, $item->quantity)) {
                $point->addWarning("Low stock for {$item->name}");
            }
        }

        return null; // Return false to cancel the operation
    }
}
```

### 3. Register Your Handler

Register handlers in a service provider:

```php
use Esegments\LaravelExtensions\Facades\Extensions;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Using a handler class
        Extensions::register(
            BeforeOrderPlaced::class,
            CheckInventoryHandler::class,
            priority: 10  // Lower = runs first
        );

        // Or use a simple closure
        Extensions::register(
            BeforeOrderPlaced::class,
            fn (BeforeOrderPlaced $point) => logger('Order being placed', [
                'order_id' => $point->order->id,
            ]),
            priority: 100
        );
    }
}
```

### 4. Dispatch the Extension Point

```php
use Esegments\LaravelExtensions\Facades\Extensions;

class OrderService
{
    public function placeOrder(Order $order, Customer $customer): OrderResult
    {
        // Create and dispatch the extension point
        $hook = new BeforeOrderPlaced($order, $customer);
        $canProceed = Extensions::dispatchInterruptible($hook);

        // Check if any handler vetoed the operation
        if (! $canProceed) {
            return OrderResult::blocked(
                reason: "Blocked by: {$hook->getInterruptedBy()}"
            );
        }

        // Check for warnings
        if (! empty($hook->warnings)) {
            // Maybe notify someone, but continue...
        }

        // Proceed with the order...
        return $this->processOrder($order);
    }
}
```

---

## Extension Point Types

### Basic Extension Point

For simple notifications or side effects:

```php
use Esegments\LaravelExtensions\Contracts\ExtensionPointContract;

class UserRegistered implements ExtensionPointContract
{
    public function __construct(
        public readonly User $user,
    ) {}
}
```

### Interruptible Extension Point

When handlers should be able to **cancel** an operation:

```php
use Esegments\LaravelExtensions\Contracts\InterruptibleContract;
use Esegments\LaravelExtensions\Concerns\InterruptibleTrait;

class BeforeAccountDelete implements InterruptibleContract
{
    use InterruptibleTrait;

    public function __construct(
        public readonly User $user,
    ) {}
}

// Handler can return false to veto
Extensions::register(BeforeAccountDelete::class, function ($point) {
    if ($point->user->hasActiveSubscription()) {
        return false; // Block deletion!
    }
});
```

### Pipeable Extension Point

When handlers should **transform** data:

```php
use Esegments\LaravelExtensions\Contracts\PipeableContract;

class CalculatePrice implements PipeableContract
{
    public function __construct(
        public readonly Product $product,
        public float $price,  // Mutable!
    ) {}
}

// Handlers modify the price in sequence
Extensions::register(CalculatePrice::class, function ($point) {
    $point->price *= 0.9;  // 10% discount
}, priority: 10);

Extensions::register(CalculatePrice::class, function ($point) {
    $point->price *= 1.08;  // Add 8% tax
}, priority: 20);
```

---

## Priority System

Handlers run in priority order. **Lower numbers run first**.

| Range | Use Case | Example |
|-------|----------|---------|
| `0-49` | Critical checks | Security validation, fraud detection |
| `50-99` | High priority | Cache invalidation, inventory checks |
| `100-149` | Normal (default) | Business logic, notifications |
| `150-199` | Low priority | Analytics, logging |
| `200+` | Background | Cleanup, stats collection |

```php
// Fraud check runs first (priority 5)
Extensions::register(BeforePayment::class, FraudDetectionHandler::class, priority: 5);

// Then inventory check (priority 50)
Extensions::register(BeforePayment::class, InventoryHandler::class, priority: 50);

// Then send notification (priority 150)
Extensions::register(BeforePayment::class, NotifyWarehouseHandler::class, priority: 150);
```

---

## Dispatcher Methods

```php
use Esegments\LaravelExtensions\Facades\Extensions;

// Basic dispatch - runs all handlers, returns the extension point
$point = Extensions::dispatch($extensionPoint);

// For interruptible - returns true if can proceed, false if vetoed
$canProceed = Extensions::dispatchInterruptible($extensionPoint);

// Silent dispatch - skips Laravel event integration
$point = Extensions::dispatchSilent($extensionPoint);

// Check if any handlers exist
if (Extensions::hasHandlers(MyExtension::class)) {
    // ...
}
```

---

## Laravel Event Integration

Extension points are also dispatched as Laravel events! This means you can use standard Laravel event listeners too:

```php
// In EventServiceProvider
protected $listen = [
    BeforeOrderPlaced::class => [
        SendSlackNotification::class,
    ],
];
```

To disable this (dispatch only to registered handlers):

```php
// config/extensions.php
'dispatch_as_events' => false,
```

---

## Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag=extensions-config
```

```php
// config/extensions.php
return [
    // Also dispatch as Laravel events?
    'dispatch_as_events' => true,

    // Clear handlers between Octane requests?
    'clear_on_octane_terminate' => false,
];
```

---

## Testing

```php
use Esegments\LaravelExtensions\Facades\Extensions;

public function test_order_can_be_blocked(): void
{
    // Clear any existing handlers
    Extensions::registry()->clear();

    // Register a handler that always blocks
    Extensions::register(
        BeforeOrderPlaced::class,
        fn () => false,  // Veto!
    );

    $point = new BeforeOrderPlaced($order, $customer);
    $canProceed = Extensions::dispatchInterruptible($point);

    $this->assertFalse($canProceed);
    $this->assertTrue($point->wasInterrupted());
}
```

---

## Comparison with Other Approaches

| Feature | Laravel Extensions | WordPress Hooks | Laravel Events |
|---------|-------------------|-----------------|----------------|
| Type safety | Full PHP classes | String names | Class-based |
| IDE support | Full autocomplete | None | Partial |
| Veto capability | Built-in | Complex | Manual |
| Priority order | Built-in | Built-in | Manual |
| Data transformation | PipeableContract | Filter hooks | Manual |
| Testability | Container-based | Global state | Good |

---

## License

MIT License. See [LICENSE](LICENSE) for details.

---

## Credits

Built with care by [Esegments](https://esegments.com).
