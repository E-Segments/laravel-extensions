---
title: "Async Processing"
description: "Queue handlers for background execution"
order: 4
---

Laravel Extensions supports asynchronous handler execution using Laravel's queue system.

## Marking Handlers as Async

Use the `#[Async]` attribute:

```php
use Esegments\LaravelExtensions\Attributes\Async;
use Esegments\LaravelExtensions\Attributes\ExtensionHandler;

#[ExtensionHandler('order.placed')]
#[Async]
class SendOrderConfirmationEmail
{
    public function __invoke($order)
    {
        Mail::to($order->customer)->send(new OrderConfirmation($order));
    }
}
```

### Specifying Queue Options

```php
#[ExtensionHandler('order.placed')]
#[Async(queue: 'emails', delay: 60)]
class SendOrderConfirmationEmail
{
    // Runs on 'emails' queue after 60 second delay
}
```

## Dispatching Async Handlers

When you dispatch an extension point, async handlers are automatically queued:

```php
// Sync handlers run immediately
// Async handlers are queued
Extensions::dispatch('order.placed', $order);
```

### Force Sync Execution

Override async for testing or debugging:

```php
Extensions::dispatch('order.placed', $order)
    ->sync();  // All handlers run synchronously
```

### Force Async Execution

Queue all handlers regardless of attributes:

```php
Extensions::dispatch('order.placed', $order)
    ->async();  // All handlers are queued
```

## Batch Processing

Process multiple extension dispatches as a batch:

```php
use Esegments\LaravelExtensions\Jobs\BatchDispatchJob;
use Illuminate\Support\Facades\Bus;

$orders = Order::pending()->get();

$jobs = $orders->map(fn($order) => new BatchDispatchJob(
    'order.process',
    $order
));

Bus::batch($jobs)
    ->name('Process pending orders')
    ->allowFailures()
    ->dispatch();
```

### Batch Callbacks

```php
Bus::batch($jobs)
    ->then(function ($batch) {
        Log::info("Batch {$batch->id} completed successfully");
    })
    ->catch(function ($batch, $e) {
        Log::error("Batch {$batch->id} failed", ['error' => $e->getMessage()]);
    })
    ->finally(function ($batch) {
        // Cleanup
    })
    ->dispatch();
```

## Queue Configuration

Configure default queue settings in `config/extensions.php`:

```php
'async' => [
    'default_queue' => 'extensions',
    'default_connection' => null,  // Uses default connection
    'retry_after' => 90,
    'max_tries' => 3,
],
```

## Job Middleware

Apply middleware to async handlers:

```php
#[ExtensionHandler('heavy.process')]
#[Async]
class HeavyProcessHandler implements ShouldQueue
{
    public function middleware(): array
    {
        return [
            new RateLimited('heavy-processing'),
            new WithoutOverlapping($this->data['id']),
        ];
    }

    public function __invoke($data)
    {
        // Process heavy task
    }
}
```

## Handling Failures

### Retry Configuration

```php
#[ExtensionHandler('external.api.call')]
#[Async(tries: 5, backoff: [10, 30, 60])]
class CallExternalApiHandler
{
    public function __invoke($data)
    {
        // Retries: immediately, 10s, 30s, 60s, 60s
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('External API call failed permanently', [
            'data' => $this->data,
            'error' => $exception->getMessage(),
        ]);
    }
}
```

### Manual Retry

```php
// In your handler
public function __invoke($data)
{
    try {
        $this->processData($data);
    } catch (TemporaryException $e) {
        // Release back to queue with delay
        $this->release(30);
    }
}
```

## Unique Jobs

Prevent duplicate job execution:

```php
#[ExtensionHandler('order.sync')]
#[Async]
class SyncOrderHandler implements ShouldBeUnique
{
    public function uniqueId(): string
    {
        return $this->data['order_id'];
    }

    public function uniqueFor(): int
    {
        return 60; // Lock for 60 seconds
    }
}
```

## Chained Async Handlers

Chain async operations:

```php
Extensions::dispatch('order.placed', $order)
    ->chain([
        fn() => Extensions::dispatch('order.notify.customer', $order),
        fn() => Extensions::dispatch('order.notify.admin', $order),
        fn() => Extensions::dispatch('order.analytics', $order),
    ]);
```

## Monitoring Async Handlers

### Using Laravel Horizon

If using Horizon, async handlers appear as regular jobs:

```php
// View in Horizon dashboard
// Job name: Esegments\LaravelExtensions\Jobs\AsyncHandlerJob
```

### Custom Tagging

```php
#[ExtensionHandler('order.process')]
#[Async]
class ProcessOrderHandler implements ShouldQueue
{
    public function tags(): array
    {
        return [
            'extension:order.process',
            'order:' . $this->data['id'],
        ];
    }
}
```

## Testing Async Handlers

### Fake the Queue

```php
use Illuminate\Support\Facades\Queue;

public function test_order_placed_queues_email()
{
    Queue::fake();

    Extensions::dispatch('order.placed', $order);

    Queue::assertPushed(AsyncHandlerJob::class, function ($job) {
        return $job->handler === SendOrderConfirmationEmail::class;
    });
}
```

### Run Queued Jobs Synchronously

```php
public function test_full_order_flow()
{
    // All async handlers run synchronously in tests
    Extensions::dispatch('order.placed', $order)->sync();

    // Assert email was sent
    Mail::assertSent(OrderConfirmation::class);
}
```

## Best Practices

### 1. Idempotent Handlers

Async handlers may run multiple times. Make them idempotent:

```php
#[Async]
class ProcessPaymentHandler
{
    public function __invoke($order)
    {
        // Check if already processed
        if ($order->payment_processed_at) {
            return;
        }

        // Process payment
        $this->paymentService->charge($order);

        // Mark as processed
        $order->update(['payment_processed_at' => now()]);
    }
}
```

### 2. Serialize Minimal Data

```php
// Good - serialize only the ID
#[Async]
class ProcessOrderHandler
{
    public function __invoke(int $orderId)
    {
        $order = Order::findOrFail($orderId);
        // Process
    }
}

// Dispatch with ID
Extensions::dispatch('order.process', $order->id);
```

### 3. Set Appropriate Timeouts

```php
#[Async(timeout: 120)]  // 2 minutes
class LongRunningHandler
{
    // ...
}
```
