<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Facades;

use Esegments\LaravelExtensions\Contracts\ExtensionPointContract;
use Esegments\LaravelExtensions\Contracts\InterruptibleContract;
use Esegments\LaravelExtensions\ExtensionDispatcher;
use Esegments\LaravelExtensions\HandlerRegistry;
use Illuminate\Support\Facades\Facade;

/**
 * @method static ExtensionPointContract dispatch(ExtensionPointContract $extensionPoint)
 * @method static bool dispatchInterruptible(InterruptibleContract $extensionPoint)
 * @method static ExtensionPointContract dispatchSilent(ExtensionPointContract $extensionPoint)
 * @method static bool hasHandlers(string $extensionPointClass)
 *
 * @see ExtensionDispatcher
 */
final class Extensions extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ExtensionDispatcher::class;
    }

    /**
     * Get the handler registry for registering handlers.
     */
    public static function registry(): HandlerRegistry
    {
        return static::getFacadeApplication()->make(HandlerRegistry::class);
    }

    /**
     * Register a handler for an extension point.
     *
     * Convenience method that delegates to HandlerRegistry.
     *
     * @param  class-string<ExtensionPointContract>  $extensionPointClass
     * @param  class-string|callable  $handler
     * @param  int  $priority  Lower values run first (default: 100)
     */
    public static function register(
        string $extensionPointClass,
        string|callable $handler,
        int $priority = 100,
    ): HandlerRegistry {
        return static::registry()->register($extensionPointClass, $handler, $priority);
    }
}
