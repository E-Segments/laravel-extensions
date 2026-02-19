---
layout: docs
title: Core Concepts
description: Understanding the extension point architecture
order: 2
---

## What Are Extension Points?

Extension points are well-defined places in your code where other parts of the application (or third-party packages) can hook in and extend functionality.

Think of them as:
- **Events** with guaranteed contracts
- **Hooks** with type safety
- **Plugin points** with IDE support

## Extension Points vs Events

| Feature | Laravel Events | Extension Points |
|---------|---------------|------------------|
| Type Safety | String-based | Class-based |
| IDE Support | Limited | Full autocompletion |
| Return Values | Via event object | Via strategies |
| Interruption | Manual | Built-in support |
| Circuit Breaker | No | Yes |
| Async | Via listeners | Built-in attributes |

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    Your Application                          │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│   dispatch(UserCreated)                                     │
│          │                                                  │
│          ▼                                                  │
│   ┌─────────────────────┐                                  │
│   │ ExtensionDispatcher │                                  │
│   └──────────┬──────────┘                                  │
│              │                                              │
│              ▼                                              │
│   ┌─────────────────────┐    ┌─────────────────────────┐  │
│   │  HandlerRegistry    │───▶│  Circuit Breaker        │  │
│   │  (Priority sorted)  │    │  (Failure protection)   │  │
│   └──────────┬──────────┘    └─────────────────────────┘  │
│              │                                              │
│              ▼                                              │
│   ┌─────────────────────────────────────────────────────┐  │
│   │              Handlers (by priority)                  │  │
│   │  ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐   │  │
│   │  │Handler 1│ │Handler 2│ │Handler 3│ │Handler 4│   │  │
│   │  │(p: 10)  │ │(p: 20)  │ │(p: 50)  │ │(p: 100) │   │  │
│   │  └─────────┘ └─────────┘ └─────────┘ └─────────┘   │  │
│   └─────────────────────────────────────────────────────┘  │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

## Key Components

### 1. Extension Point

A class that represents a hookable moment:

```php
class OrderPlaced implements ExtensionPointContract
{
    public function __construct(
        public readonly Order $order,
        public readonly User $customer
    ) {}
}
```

### 2. Handler

A class that responds to an extension point:

```php
class SendOrderConfirmation implements ExtensionHandlerContract
{
    public function handle(ExtensionPointContract $extension): void
    {
        // React to the extension point
    }
}
```

### 3. Registry

Maintains the mapping of extension points to handlers:

```php
Extensions::register(OrderPlaced::class, SendOrderConfirmation::class);
Extensions::register(OrderPlaced::class, UpdateInventory::class);
Extensions::register(OrderPlaced::class, NotifyWarehouse::class);
```

### 4. Dispatcher

Executes handlers when an extension point is dispatched:

```php
Extensions::dispatch(new OrderPlaced($order, $customer));
```

## Handler Priorities

Handlers execute in priority order (lower = earlier):

| Priority Range | Use Case | Examples |
|---------------|----------|----------|
| 0-49 | Critical | Security checks, validation |
| 50-99 | High | Cache invalidation |
| 100-149 | Normal | Business logic |
| 150-199 | Low | Notifications |
| 200+ | Very Low | Analytics, logging |

```php
Extensions::register(OrderPlaced::class, ValidateOrder::class, priority: 10);
Extensions::register(OrderPlaced::class, ProcessPayment::class, priority: 50);
Extensions::register(OrderPlaced::class, SendEmail::class, priority: 150);
Extensions::register(OrderPlaced::class, TrackAnalytics::class, priority: 200);
```

## Interruptible Extension Points

Some extension points can be interrupted (vetoed):

```php
class CanDeleteUser implements ExtensionPointContract, InterruptibleContract
{
    public function __construct(public readonly User $user) {}
}
```

Handlers can prevent the action:

```php
class PreventAdminDeletion implements ExtensionHandlerContract
{
    public function handle(ExtensionPointContract $extension): bool
    {
        if ($extension->user->isAdmin()) {
            return false; // Veto!
        }
        return true; // Allow
    }
}
```

Dispatch and check:

```php
$canDelete = Extensions::dispatchInterruptible(new CanDeleteUser($user));

if (!$canDelete) {
    return back()->with('error', 'Cannot delete this user');
}
```

## Contracts Overview

| Contract | Purpose |
|----------|---------|
| `ExtensionPointContract` | Marker for extension points |
| `ExtensionHandlerContract` | Handler must implement `handle()` |
| `InterruptibleContract` | Extension can be vetoed |
| `AsyncHandlerContract` | Handler runs asynchronously |
| `PipeableContract` | Supports pipeline transformation |
