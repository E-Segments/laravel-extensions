---
layout: docs
title: Getting Started
description: Install and configure Laravel Extensions
---

## Installation

```bash
composer require esegments/laravel-extensions
```

## Publish Configuration

```bash
php artisan vendor:publish --tag=extensions-config
```

## Core Concepts

Laravel Extensions provides a **type-safe extension point system** for Laravel applications. Unlike WordPress-style string hooks, extension points are defined as classes, giving you:

- Full IDE autocompletion
- Type safety
- Refactoring support
- Clear contracts

## Quick Example

### 1. Define an Extension Point

```php
namespace App\Extensions;

use Esegments\LaravelExtensions\Contracts\ExtensionPointContract;

class UserCreated implements ExtensionPointContract
{
    public function __construct(
        public readonly User $user
    ) {}
}
```

### 2. Create a Handler

```php
namespace App\Handlers;

use Esegments\LaravelExtensions\Contracts\ExtensionHandlerContract;

class SendWelcomeEmail implements ExtensionHandlerContract
{
    public function handle(ExtensionPointContract $extension): void
    {
        Mail::to($extension->user->email)
            ->send(new WelcomeEmail($extension->user));
    }
}
```

### 3. Register the Handler

```php
// In a service provider
use Esegments\LaravelExtensions\Facades\Extensions;

Extensions::register(
    UserCreated::class,
    SendWelcomeEmail::class
);
```

### 4. Dispatch the Extension Point

```php
use Esegments\LaravelExtensions\Facades\Extensions;
use App\Extensions\UserCreated;

// In your controller or service
$user = User::create($data);

Extensions::dispatch(new UserCreated($user));
```

## Multiple Handlers

Extension points can have multiple handlers:

```php
// Register multiple handlers for the same extension point
Extensions::register(UserCreated::class, SendWelcomeEmail::class, priority: 10);
Extensions::register(UserCreated::class, CreateUserProfile::class, priority: 20);
Extensions::register(UserCreated::class, NotifyAdmins::class, priority: 30);
Extensions::register(UserCreated::class, TrackAnalytics::class, priority: 100);
```

Handlers execute in priority order (lower numbers first).

## Using Attributes

Alternatively, use PHP attributes:

```php
use Esegments\LaravelExtensions\Attributes\ExtensionHandler;

#[ExtensionHandler(UserCreated::class, priority: 10)]
class SendWelcomeEmail implements ExtensionHandlerContract
{
    public function handle(ExtensionPointContract $extension): void
    {
        // ...
    }
}
```

Enable attribute discovery:

```php
// config/extensions.php
'discovery' => [
    'enabled' => true,
    'directories' => ['app/Handlers'],
],
```

## Basic Configuration

```php
// config/extensions.php
return [
    'debug' => env('EXTENSIONS_DEBUG', false),
    'graceful_mode' => env('EXTENSIONS_GRACEFUL', false),
    
    'circuit_breaker' => [
        'enabled' => true,
        'threshold' => 5,
        'timeout' => 60,
    ],
];
```

## Next Steps

- [Core Concepts](/docs/concepts/) - Understand the architecture
- [Extension Points](/docs/extension-points/) - Creating extension points
- [Handlers](/docs/handlers/) - Registering and managing handlers
- [Safety Features](/docs/graceful-mode/) - Error handling and resilience
