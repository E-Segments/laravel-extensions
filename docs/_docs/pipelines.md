---
title: "Pipelines"
description: "Chain handlers together with data transformation"
order: 12
parent: "Handlers"
---

Pipelines allow you to chain multiple handlers together, passing data through each stage with optional transformations.

## Basic Pipeline

```php
use Esegments\LaravelExtensions\Pipeline\ExtensionPipeline;

$result = ExtensionPipeline::make()
    ->through([
        ValidateOrderHandler::class,
        CalculateTaxHandler::class,
        ApplyDiscountsHandler::class,
        FinalizeOrderHandler::class,
    ])
    ->send($order)
    ->thenReturn();
```

## Pipeline with Extensions

Combine pipelines with extension points:

```php
use Esegments\LaravelExtensions\Facades\Extensions;

// Register pipeline stages via extensions
Extensions::register('order.process', ValidateOrderHandler::class, 100);
Extensions::register('order.process', CalculateTaxHandler::class, 90);
Extensions::register('order.process', ApplyDiscountsHandler::class, 80);

// Execute as pipeline
$result = Extensions::dispatch('order.process', $order)
    ->asPipeline()
    ->thenReturn();
```

## Transformation Between Stages

Transform data between pipeline stages:

```php
ExtensionPipeline::make()
    ->through([
        function ($data, $next) {
            // Pre-process
            $data['started_at'] = now();

            // Pass to next stage
            $result = $next($data);

            // Post-process
            $result['completed_at'] = now();

            return $result;
        },
        ProcessDataHandler::class,
    ])
    ->send($data)
    ->thenReturn();
```

## Conditional Stages

Add stages conditionally:

```php
$pipeline = ExtensionPipeline::make()
    ->through([
        ValidateHandler::class,
    ]);

if ($needsApproval) {
    $pipeline->pipe(ApprovalHandler::class);
}

$pipeline->pipe(FinalizeHandler::class);

$result = $pipeline->send($data)->thenReturn();
```

## Pipeline with Fallback

Handle failures gracefully:

```php
$result = ExtensionPipeline::make()
    ->through($handlers)
    ->send($data)
    ->onFailure(function ($exception, $data) {
        Log::error('Pipeline failed', [
            'error' => $exception->getMessage(),
            'data' => $data,
        ]);

        return $this->getDefaultResult($data);
    })
    ->thenReturn();
```

## Creating Pipeline Handlers

Pipeline handlers receive the data and a closure to call the next stage:

```php
class CalculateTaxHandler
{
    public function handle($order, Closure $next)
    {
        // Calculate tax
        $order->tax = $order->subtotal * 0.1;

        // Pass to next handler
        return $next($order);
    }
}
```

### Invokable Handlers

```php
class CalculateTaxHandler
{
    public function __invoke($order, Closure $next)
    {
        $order->tax = $this->taxService->calculate($order);

        return $next($order);
    }
}
```

## Aborting a Pipeline

Stop pipeline execution early:

```php
class ValidateOrderHandler
{
    public function handle($order, Closure $next)
    {
        if (!$order->isValid()) {
            // Don't call $next - pipeline stops here
            return [
                'success' => false,
                'error' => 'Order validation failed',
            ];
        }

        return $next($order);
    }
}
```

## Pipeline Events

Listen to pipeline lifecycle events:

```php
ExtensionPipeline::make()
    ->through($handlers)
    ->beforeEach(function ($handler, $data) {
        Log::debug("Starting handler: " . get_class($handler));
    })
    ->afterEach(function ($handler, $data, $result) {
        Log::debug("Completed handler: " . get_class($handler));
    })
    ->send($data)
    ->thenReturn();
```

## Nested Pipelines

Compose pipelines within pipelines:

```php
class OrderProcessingHandler
{
    public function handle($order, Closure $next)
    {
        // Run sub-pipeline for order items
        foreach ($order->items as $item) {
            ExtensionPipeline::make()
                ->through([
                    ValidateItemHandler::class,
                    CalculateItemPriceHandler::class,
                ])
                ->send($item)
                ->thenReturn();
        }

        return $next($order);
    }
}
```

## Best Practices

### 1. Keep Handlers Focused

Each handler should do one thing:

```php
// Good - single responsibility
class CalculateTaxHandler { }
class ApplyDiscountHandler { }
class UpdateInventoryHandler { }

// Bad - too many responsibilities
class ProcessEverythingHandler { }
```

### 2. Use Dependency Injection

```php
class NotifyCustomerHandler
{
    public function __construct(
        private NotificationService $notifications
    ) {}

    public function handle($order, Closure $next)
    {
        $this->notifications->send($order->customer, new OrderConfirmation($order));

        return $next($order);
    }
}
```

### 3. Handle Errors Appropriately

```php
class ExternalApiHandler
{
    public function handle($data, Closure $next)
    {
        try {
            $response = $this->api->call($data);
            $data['api_response'] = $response;
        } catch (ApiException $e) {
            // Decide: abort or continue with default
            $data['api_response'] = $this->getDefaultResponse();
        }

        return $next($data);
    }
}
```
