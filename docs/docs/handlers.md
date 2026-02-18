---
layout: docs
title: Registering Handlers
description: How to register and manage extension handlers
---

## Basic Registration

Register handlers in a service provider:

```php
use Esegments\LaravelExtensions\Facades\Extensions;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Extensions::register(
            UserCreated::class,      // Extension point
            SendWelcomeEmail::class, // Handler
            priority: 10             // Optional priority
        );
    }
}
```

## Handler Contract

Handlers must implement `ExtensionHandlerContract`:

```php
use Esegments\LaravelExtensions\Contracts\ExtensionHandlerContract;
use Esegments\LaravelExtensions\Contracts\ExtensionPointContract;

class SendWelcomeEmail implements ExtensionHandlerContract
{
    public function handle(ExtensionPointContract $extension): void
    {
        Mail::to($extension->user->email)
            ->send(new WelcomeEmail($extension->user));
    }
}
```

## Type-Hinted Handlers

For better IDE support, type-hint the specific extension:

```php
class SendWelcomeEmail implements ExtensionHandlerContract
{
    public function handle(UserCreated $extension): void
    {
        // Full autocompletion for $extension->user
        Mail::to($extension->user->email)
            ->send(new WelcomeEmail($extension->user));
    }
}
```

## Closure Handlers

For simple handlers, use closures:

```php
Extensions::register(UserCreated::class, function (UserCreated $extension) {
    Log::info('User created', ['user_id' => $extension->user->id]);
});
```

## Dependency Injection

Handlers are resolved from the container:

```php
class SendWelcomeEmail implements ExtensionHandlerContract
{
    public function __construct(
        private readonly Mailer $mailer,
        private readonly UserRepository $users
    ) {}

    public function handle(UserCreated $extension): void
    {
        $this->mailer->send(
            new WelcomeEmail($extension->user)
        );
    }
}
```

## Registration Methods

### Single Handler

```php
Extensions::register(UserCreated::class, SendWelcomeEmail::class);
```

### With Priority

```php
Extensions::register(UserCreated::class, SendWelcomeEmail::class, priority: 10);
```

### With Tags

```php
Extensions::registerWithTags(
    UserCreated::class,
    SendWelcomeEmail::class,
    priority: 10,
    tags: ['notifications', 'email']
);
```

### Multiple Handlers at Once

```php
Extensions::registerMany([
    [UserCreated::class, SendWelcomeEmail::class, 10],
    [UserCreated::class, CreateProfile::class, 20],
    [UserCreated::class, TrackAnalytics::class, 100],
]);
```

### Handler Groups

```php
Extensions::registerGroup('user-notifications', [
    [UserCreated::class, SendWelcomeEmail::class, 10],
    [UserUpdated::class, SendProfileUpdateEmail::class, 10],
    [PasswordReset::class, SendPasswordResetEmail::class, 10],
]);

// Later, disable entire group
Extensions::disableGroup('user-notifications');
```

## Removing Handlers

```php
// Remove specific handler
Extensions::unregister(UserCreated::class, SendWelcomeEmail::class);

// Remove all handlers for extension point
Extensions::unregisterAll(UserCreated::class);
```

## Checking Registration

```php
// Check if extension point has any handlers
Extensions::hasHandlers(UserCreated::class);

// Count handlers
Extensions::countHandlers(UserCreated::class);

// Get all handlers for extension point
Extensions::getHandlers(UserCreated::class);

// Get all registered extension points
Extensions::getRegisteredExtensionPoints();
```

## Handler Return Values

By default, handler return values are ignored:

```php
public function handle(UserCreated $extension): void
{
    // Return value ignored
}
```

To use return values, see [Strategies](/docs/strategies/).

## Handler Ordering

Handlers execute by priority (lower numbers first):

```php
Extensions::register(OrderPlaced::class, ValidateStock::class, priority: 10);
Extensions::register(OrderPlaced::class, ProcessPayment::class, priority: 20);
Extensions::register(OrderPlaced::class, UpdateInventory::class, priority: 30);
Extensions::register(OrderPlaced::class, SendConfirmation::class, priority: 100);
```

Execution order: ValidateStock → ProcessPayment → UpdateInventory → SendConfirmation
