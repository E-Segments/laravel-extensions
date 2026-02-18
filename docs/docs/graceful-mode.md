---
layout: docs
title: Graceful Mode
description: Handle errors without breaking execution
---

## Overview

Graceful mode catches handler exceptions instead of throwing them, allowing all handlers to execute even if some fail. Errors are collected and can be inspected after dispatch.

## Enabling Graceful Mode

### Per-Dispatch

```php
use Esegments\LaravelExtensions\Facades\Extensions;

$result = Extensions::gracefully()->dispatch(new UserCreated($user));

// Check for errors
if ($result->hasErrors()) {
    foreach ($result->errors() as $handlerClass => $exception) {
        Log::error("Handler failed: {$handlerClass}", [
            'error' => $exception->getMessage()
        ]);
    }
}
```

### Globally

In `config/extensions.php`:

```php
'graceful_mode' => true,
```

Or via environment:

```env
EXTENSIONS_GRACEFUL=true
```

## DispatchResult Object

When using graceful mode with `dispatchWithResults()`:

```php
$result = Extensions::gracefully()
    ->dispatchWithResults(new OrderPlaced($order));

// Get the extension point back
$extension = $result->extension();

// Check for any errors
$result->hasErrors(); // bool

// Get all errors (handler class => exception)
$result->errors(); // array

// Get successful handler results
$result->results(); // array

// Check if all handlers succeeded
$result->successful(); // bool

// Check if any handler failed
$result->failed(); // bool
```

## Use Cases

### 1. Non-Critical Handlers

When some handlers failing shouldn't stop others:

```php
// All these should run even if one fails
Extensions::register(UserCreated::class, SendWelcomeEmail::class);
Extensions::register(UserCreated::class, CreateProfile::class);
Extensions::register(UserCreated::class, TrackAnalytics::class);
Extensions::register(UserCreated::class, SyncToCRM::class);

// Graceful dispatch ensures all run
Extensions::gracefully()->dispatch(new UserCreated($user));
```

### 2. Collecting Validation Errors

```php
$result = Extensions::gracefully()
    ->dispatchWithResults(new ValidateOrder($order));

if ($result->hasErrors()) {
    return back()->withErrors(
        collect($result->errors())->map->getMessage()
    );
}
```

### 3. Logging All Failures

```php
$result = Extensions::gracefully()->dispatch(new ProcessBatch($items));

// Log all failures for investigation
foreach ($result->errors() as $handler => $exception) {
    Log::channel('batch-processing')->error("Handler {$handler} failed", [
        'exception' => $exception,
        'batch_id' => $items->id,
    ]);
}

// Continue even with partial failures
if ($result->partialSuccess()) {
    $items->markAsPartiallyProcessed();
}
```

## Strict Mode

The opposite of graceful - throws if no handlers registered:

```php
// Throws StrictModeException if no handlers
Extensions::strictly()->dispatch(new MustHaveHandlers($data));
```

Useful for ensuring critical extension points always have handlers.

## Combining with Strategies

Graceful mode works with all strategies:

```php
// Collect all results, catching errors
$prices = Extensions::gracefully()
    ->mergeResults()
    ->dispatch(new GetPriceQuotes($product));

// First result, skip failed handlers
$result = Extensions::gracefully()
    ->firstResult()
    ->dispatch(new CalculateShipping($order));
```

## Best Practices

### 1. Always Check Errors in Production

```php
$result = Extensions::gracefully()->dispatch($extension);

if ($result->hasErrors()) {
    // Log for monitoring
    Log::warning('Extension handlers failed', [
        'extension' => get_class($extension),
        'errors' => collect($result->errors())->map->getMessage(),
    ]);
    
    // Maybe notify if critical
    if ($this->isCritical($extension)) {
        $this->notifyOperations($result->errors());
    }
}
```

### 2. Use for Optional Integrations

```php
// CRM sync might fail but order should still process
Extensions::gracefully()->dispatch(new OrderPlaced($order));
```

### 3. Don't Use for Critical Operations

```php
// Payment must succeed - don't use graceful
try {
    Extensions::dispatch(new ProcessPayment($order));
} catch (PaymentException $e) {
    return back()->with('error', 'Payment failed');
}
```
