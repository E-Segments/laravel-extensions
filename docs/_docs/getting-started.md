---
title: "Getting Started"
description: "Learn how to install and use Laravel Extensions"
order: 1
---

## Requirements

- PHP 8.2+
- Laravel 11+

## Installation

Install via Composer:

```bash
composer require esegments/laravel-extensions
```

Publish the configuration:

```bash
php artisan vendor:publish --provider="Esegments\LaravelExtensions\ExtensionServiceProvider"
```

This creates `config/extensions.php`.

## Basic Usage

### Defining Extension Points

Extension points are hooks in your code where plugins can add functionality:

```php
use Esegments\LaravelExtensions\Facades\Extensions;

class UserService
{
    public function register(array $data)
    {
        $user = User::create($data);

        // Dispatch extension point
        Extensions::dispatch('user.registered', $user);

        return $user;
    }
}
```

### Registering Handlers

Register handlers in a service provider:

```php
use Esegments\LaravelExtensions\Facades\Extensions;

class AppServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Extensions::register('user.registered', function ($user) {
            Mail::to($user)->send(new WelcomeEmail($user));
        });

        // With priority (higher = runs first)
        Extensions::register('user.registered', CreateUserProfile::class, 20);
    }
}
```

### Using Attributes

PHP 8 attributes provide a cleaner way to register handlers:

```php
use Esegments\LaravelExtensions\Attributes\ExtensionHandler;

#[ExtensionHandler('user.registered', priority: 10)]
class SendWelcomeEmail
{
    public function __invoke($user)
    {
        Mail::to($user)->send(new WelcomeEmail($user));
    }
}
```

Enable attribute discovery in `config/extensions.php`:

```php
'discovery' => [
    'enabled' => true,
    'paths' => [
        app_path('Extensions'),
    ],
],
```

## Working with Results

### Getting Results

```php
$results = Extensions::dispatch('order.calculating', $order);

// Get all results as array
$allResults = $results->all();

// Check if any handler succeeded
if ($results->hasSuccess()) {
    // ...
}

// Get first successful result
$first = $results->first();
```

### Result Strategies

Use different strategies to combine handler results:

```php
// First non-null result
$result = Extensions::dispatch('get.price', $product)
    ->useStrategy(new FirstResultStrategy())
    ->value();

// Merge all arrays
$combined = Extensions::dispatch('collect.data', $context)
    ->useStrategy(new MergeResultsStrategy())
    ->value();

// Reduce with callback
$total = Extensions::dispatch('calculate.fees', $order)
    ->useStrategy(new ReduceResultsStrategy(
        fn($carry, $item) => $carry + $item,
        0
    ))
    ->value();
```

## Conditional Handlers

### Environment-Based

```php
use Esegments\LaravelExtensions\Attributes\When;

#[ExtensionHandler('debug.log')]
#[When(env: 'local')]
class LocalDebugHandler
{
    public function __invoke($data)
    {
        Log::debug('Debug data', $data);
    }
}
```

### Feature Flag-Based

```php
use Esegments\LaravelExtensions\Attributes\WhenFeature;

#[ExtensionHandler('checkout.complete')]
#[WhenFeature('new-checkout')]
class NewCheckoutHandler
{
    // Only runs when 'new-checkout' feature is enabled
}
```

## Interruptible Flow

Handlers can stop further processing:

```php
Extensions::register('order.validate', function ($order) {
    if ($order->total > 10000) {
        // Stop processing, return error
        return Extensions::interrupt([
            'error' => 'Order exceeds limit',
            'max' => 10000,
        ]);
    }
});
```

## Next Steps

- [Safety Features](/docs/safety/) - Circuit breakers and error handling
- [Pipelines](/docs/pipelines/) - Chain handlers together
- [Async Processing](/docs/async/) - Queue handlers for background work
- [Configuration](/docs/configuration/) - All options explained
