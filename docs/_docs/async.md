---
layout: docs
title: Async Processing
description: Execute handlers asynchronously via queues
order: 30
parent: "Advanced"
---

## Overview

Handlers can be executed asynchronously using Laravel's queue system. This is useful for time-consuming operations that don't need to complete before returning a response.

## Using the Async Attribute

```php
use Esegments\LaravelExtensions\Attributes\Async;
use Esegments\LaravelExtensions\Attributes\ExtensionHandler;
use Esegments\LaravelExtensions\Contracts\AsyncHandlerContract;

#[ExtensionHandler(OrderPlaced::class)]
#[Async(
    queue: 'notifications',
    delay: 0,
    retries: 3
)]
class SendOrderNotification implements AsyncHandlerContract
{
    public function handle(ExtensionPointContract $extension): void
    {
        // This runs in the background
        $extension->order->customer->notify(
            new OrderConfirmationNotification($extension->order)
        );
    }
}
```

## Async Attribute Options

```php
#[Async(
    queue: 'high-priority',      // Queue name
    delay: 60,                    // Delay in seconds
    retries: 3,                   // Max attempts
    backoff: 'exponential',       // Backoff strategy
    backoffSeconds: 10,           // Initial backoff
    timeout: 120,                 // Job timeout in seconds
    onFailure: NotifyAdmin::class,// Failure callback
    onRetry: LogRetry::class,     // Retry callback
    uniqueJob: false,             // Prevent duplicates
    uniqueLockTimeout: 3600,      // Unique lock duration
)]
```

## Configuration

Default async settings in `config/extensions.php`:

```php
'async' => [
    'default_queue' => 'default',
    'tries' => 3,
    'backoff' => 10,
    'backoff_strategy' => 'exponential',
],
```

## Backoff Strategies

### Linear

Wait increases by fixed amount: 10s, 20s, 30s...

```php
#[Async(backoff: 'linear', backoffSeconds: 10)]
```

### Exponential

Wait doubles each time: 10s, 20s, 40s, 80s...

```php
#[Async(backoff: 'exponential', backoffSeconds: 10)]
```

### Fixed

Same wait each time: 10s, 10s, 10s...

```php
#[Async(backoff: 'fixed', backoffSeconds: 10)]
```

### Custom Array

Specific delays for each retry:

```php
#[Async(backoffSeconds: [10, 30, 60, 300])]
```

## Failure Handling

### onFailure Callback

```php
#[Async(onFailure: NotifyOnFailure::class)]
class SendEmailHandler implements AsyncHandlerContract
{
    // ...
}

class NotifyOnFailure
{
    public function __invoke(Throwable $exception, ExtensionPointContract $extension): void
    {
        Log::error('Email handler failed', [
            'exception' => $exception->getMessage(),
            'extension' => get_class($extension),
        ]);
        
        // Notify operations team
        Slack::send("Handler failed: " . $exception->getMessage());
    }
}
```

### onRetry Callback

```php
#[Async(onRetry: LogRetryAttempt::class)]
class ProcessPaymentHandler implements AsyncHandlerContract
{
    // ...
}

class LogRetryAttempt
{
    public function __invoke(int $attempt, Throwable $exception): void
    {
        Log::warning("Retry attempt {$attempt}", [
            'error' => $exception->getMessage(),
        ]);
    }
}
```

## Unique Jobs

Prevent duplicate jobs for the same extension:

```php
#[Async(
    uniqueJob: true,
    uniqueLockTimeout: 3600  // Lock for 1 hour
)]
class ImportProductsHandler implements AsyncHandlerContract
{
    // Only one import job per product catalog at a time
}
```

## Programmatic Async

Register async handlers programmatically:

```php
Extensions::registerAsync(
    OrderPlaced::class,
    SendEmailHandler::class,
    queue: 'emails',
    delay: 30,
    retries: 5
);
```

## Mixing Sync and Async

You can have both sync and async handlers:

```php
// Sync - runs immediately
Extensions::register(OrderPlaced::class, UpdateInventory::class, priority: 10);
Extensions::register(OrderPlaced::class, ProcessPayment::class, priority: 20);

// Async - queued for background
Extensions::registerAsync(OrderPlaced::class, SendConfirmation::class);
Extensions::registerAsync(OrderPlaced::class, GenerateInvoice::class);
```

Sync handlers execute first, then async handlers are queued.

## Monitoring Async Jobs

Use Laravel Horizon or similar tools to monitor:

```bash
php artisan horizon
```

Or check queue status:

```bash
php artisan queue:work --queue=notifications
```

## Best Practices

### 1. Serialize Extension Points Carefully

Extension points are serialized for the queue. Ensure models use `SerializesModels`:

```php
class OrderPlaced implements ExtensionPointContract
{
    use SerializesModels;
    
    public function __construct(
        public readonly Order $order
    ) {}
}
```

### 2. Use Appropriate Queues

```php
// Time-critical
#[Async(queue: 'high')]
class SendPaymentReceipt { }

// Can wait
#[Async(queue: 'low')]
class GenerateReports { }

// Resource-intensive
#[Async(queue: 'heavy')]
class ProcessImages { }
```

### 3. Set Reasonable Timeouts

```php
#[Async(timeout: 30)]   // Quick tasks
#[Async(timeout: 300)]  // Medium tasks
#[Async(timeout: 3600)] // Long-running tasks
```
