---
layout: docs
title: Strategies
description: Control how handler results are aggregated
---

## Overview

Strategies determine how results from multiple handlers are combined. By default, handlers execute but their return values are ignored. Strategies let you collect, merge, or reduce results.

## Available Strategies

| Strategy | Purpose | Returns |
|----------|---------|---------|
| **FirstResult** | Get first non-null result | Single value |
| **MergeResults** | Combine all results | Array or Collection |
| **ReduceResults** | Reduce to single value | Computed value |

## FirstResult Strategy

Returns the first non-null handler result and stops execution:

```php
use Esegments\LaravelExtensions\Facades\Extensions;

// First handler to return a non-null value wins
$price = Extensions::firstResult()->dispatch(new CalculatePrice($product));
```

### Example

```php
// Handler 1: Returns null (skipped)
class DefaultPriceHandler {
    public function handle($ext): ?float {
        return null; // No opinion
    }
}

// Handler 2: Returns value (used)
class DiscountPriceHandler {
    public function handle($ext): ?float {
        if ($ext->product->hasDiscount()) {
            return $ext->product->discounted_price;
        }
        return null;
    }
}

// Handler 3: Never reached (first result already found)
class FallbackPriceHandler {
    public function handle($ext): float {
        return $ext->product->base_price;
    }
}
```

## MergeResults Strategy

Collects all handler results into an array or collection:

```php
// As array
$errors = Extensions::mergeResults()
    ->dispatch(new ValidateOrder($order));

// As collection
$errors = Extensions::mergeResults(asCollection: true)
    ->dispatch(new ValidateOrder($order));

// Flatten nested arrays
$errors = Extensions::mergeResults(flattenArrays: true)
    ->dispatch(new ValidateOrder($order));
```

### Example: Collecting Validation Errors

```php
class CheckInventoryHandler {
    public function handle($ext): array {
        $errors = [];
        foreach ($ext->order->items as $item) {
            if (!$item->product->inStock($item->quantity)) {
                $errors[] = "{$item->product->name} is out of stock";
            }
        }
        return $errors;
    }
}

class CheckAddressHandler {
    public function handle($ext): array {
        if (!$ext->order->shippingAddress->isValid()) {
            return ['Invalid shipping address'];
        }
        return [];
    }
}

// Dispatch
$allErrors = Extensions::mergeResults(flattenArrays: true)
    ->dispatch(new ValidateOrder($order));

if (!empty($allErrors)) {
    return back()->withErrors($allErrors);
}
```

### Fluent API

```php
Extensions::mergeResults()
    ->asCollection()      // Return as Collection
    ->preserveNesting()   // Don't flatten arrays
    ->dispatch($extension);
```

## ReduceResults Strategy

Reduces all results using a callback function:

```php
// Sum all results
$total = Extensions::reduceResults(
    fn ($carry, $result) => $carry + $result,
    initial: 0
)->dispatch(new CalculateOrderTotal($order));
```

### Built-in Reducers

```php
use Esegments\LaravelExtensions\Strategies\ReduceResultsStrategy;

// Sum numbers
$total = Extensions::reduceResults(ReduceResultsStrategy::sum())
    ->dispatch($extension);

// Check all true
$allValid = Extensions::reduceResults(ReduceResultsStrategy::allTrue())
    ->dispatch(new ValidateAll($data));

// Check any true
$hasPermission = Extensions::reduceResults(ReduceResultsStrategy::anyTrue())
    ->dispatch(new CheckPermissions($user, $resource));

// Count results
$count = Extensions::reduceResults(ReduceResultsStrategy::count())
    ->dispatch($extension);

// Find minimum
$min = Extensions::reduceResults(ReduceResultsStrategy::min())
    ->dispatch(new GetPriceQuotes($product));

// Find maximum
$max = Extensions::reduceResults(ReduceResultsStrategy::max())
    ->dispatch(new GetBidAmounts($auction));
```

### Custom Reducers

```php
// Concatenate strings
$combined = Extensions::reduceResults(
    fn ($carry, $result) => $carry . $result,
    initial: ''
)->dispatch($extension);

// Collect into array with transformation
$transformed = Extensions::reduceResults(
    fn ($carry, $result) => [...$carry, strtoupper($result)],
    initial: []
)->dispatch($extension);

// Complex reduction
$stats = Extensions::reduceResults(
    function ($carry, $result) {
        $carry['total'] += $result['amount'];
        $carry['count']++;
        return $carry;
    },
    initial: ['total' => 0, 'count' => 0]
)->dispatch($extension);
```

## Combining with Other Features

### With Graceful Mode

```php
$result = Extensions::gracefully()
    ->mergeResults()
    ->dispatch($extension);

// Check for errors
if ($result->hasErrors()) {
    Log::error('Some handlers failed', $result->errors());
}
```

### With Circuit Breaker

Strategies work with circuit breaker automatically - handlers with open circuits are skipped:

```php
$prices = Extensions::mergeResults()
    ->dispatch(new GetPriceQuotes($product));

// Handlers with open circuits are skipped, not included in results
```

## Creating Custom Strategies

Implement `ResultStrategyContract`:

```php
use Esegments\LaravelExtensions\Contracts\ResultStrategyContract;

class AverageResultsStrategy implements ResultStrategyContract
{
    private array $results = [];

    public function collect(mixed $result): void
    {
        if (is_numeric($result)) {
            $this->results[] = $result;
        }
    }

    public function getResult(): float
    {
        if (empty($this->results)) {
            return 0;
        }
        return array_sum($this->results) / count($this->results);
    }
}

// Usage
$average = Extensions::withStrategy(new AverageResultsStrategy())
    ->dispatch(new GetRatings($product));
```
