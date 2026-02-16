---
layout: landing
---

<div class="text-center mb-16">
  <h1 class="text-5xl font-bold mb-6">Laravel Extensions</h1>
  <p class="text-xl text-gray-600 dark:text-gray-400 max-w-2xl mx-auto mb-8">
    A powerful, flexible extension point system for Laravel applications. Build extensible, plugin-ready applications with a clean event-driven architecture.
  </p>
  <div class="flex gap-4 justify-center">
    <a href="/laravel-extensions/docs/getting-started/" class="px-6 py-3 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition">
      Get Started
    </a>
    <a href="https://github.com/E-Segments/laravel-extensions" class="px-6 py-3 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 transition">
      View on GitHub
    </a>
  </div>
</div>

<div class="not-prose cards-grid mb-16">
  <div class="card">
    <div class="card-title">Extension Points</div>
    <div class="card-description">Define hook points throughout your application for plugins to extend</div>
  </div>
  <div class="card">
    <div class="card-title">Circuit Breaker</div>
    <div class="card-description">Automatic fault tolerance with configurable thresholds and recovery</div>
  </div>
  <div class="card">
    <div class="card-title">Pipelines</div>
    <div class="card-description">Chain handlers together with result strategies and transformations</div>
  </div>
  <div class="card">
    <div class="card-title">Async Support</div>
    <div class="card-description">Queue handlers for background processing with batch support</div>
  </div>
</div>

## Features

### Core Extension System

- **Extension Points** - Define named hooks throughout your application
- **Handler Registry** - Register handlers with priorities and conditions
- **Dispatch Results** - Standardized result objects with success/failure states
- **Interruptible Flow** - Allow handlers to stop propagation

### Safety & Reliability

| Feature | Description |
|---------|-------------|
| Circuit Breaker | Automatic failure detection and recovery |
| Graceful Execution | Error handling without crashes |
| Mutable Trait | Suppress exceptions when needed |
| Silenceable Trait | Control output suppression |

### Advanced Patterns

- **Pipelines** - Chain handlers with data transformation
- **Result Strategies** - First result, merge, or reduce
- **Async Dispatch** - Queue handlers for background processing
- **Batch Processing** - Process multiple extensions in parallel

### Developer Experience

- **PHP 8 Attributes** - Declarative handler registration
- **Profiling** - Performance monitoring and bottleneck detection
- **Signature Validation** - Ensure handler compatibility
- **IDE Support** - Helper generation for autocomplete

## Installation

```bash
composer require esegments/laravel-extensions
```

```bash
php artisan vendor:publish --provider="Esegments\LaravelExtensions\ExtensionServiceProvider"
```

## Quick Example

Define an extension point:

```php
use Esegments\LaravelExtensions\Facades\Extensions;

// In your application code
$results = Extensions::dispatch('user.registered', $user);
```

Register a handler:

```php
// In a service provider
Extensions::register('user.registered', function ($user) {
    // Send welcome email, create profile, etc.
    return ['emailed' => true];
});
```

Or use attributes:

```php
use Esegments\LaravelExtensions\Attributes\ExtensionHandler;

#[ExtensionHandler('user.registered', priority: 10)]
class SendWelcomeEmail
{
    public function __invoke($user)
    {
        Mail::to($user)->send(new WelcomeEmail($user));
        return ['emailed' => true];
    }
}
```

<div class="callout callout-info">
  <strong>Next Steps:</strong> Check out the <a href="/laravel-extensions/docs/getting-started/">Getting Started guide</a> for a complete walkthrough.
</div>
