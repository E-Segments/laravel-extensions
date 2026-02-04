<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions;

use Closure;
use Esegments\LaravelExtensions\Contracts\ExtensionHandlerContract;
use Esegments\LaravelExtensions\Contracts\ExtensionPointContract;
use Esegments\LaravelExtensions\Contracts\InterruptibleContract;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use InvalidArgumentException;

/**
 * Dispatches extension points to registered handlers.
 *
 * The dispatcher:
 * - Resolves handler classes from the container
 * - Executes handlers in priority order (lower values first)
 * - Supports interruption for InterruptibleContract
 * - Optionally dispatches to Laravel's event system for interoperability
 */
final class ExtensionDispatcher
{
    public function __construct(
        private readonly Container $container,
        private readonly HandlerRegistry $registry,
        private readonly ?EventDispatcher $events = null,
        private readonly bool $dispatchAsEvents = true,
    ) {}

    /**
     * Dispatch an extension point to all registered handlers.
     *
     * Returns the extension point after all handlers have processed it.
     * For PipeableContract, handlers may have modified the extension point's data.
     *
     * @template T of ExtensionPointContract
     *
     * @param  T  $extensionPoint
     * @return T
     */
    public function dispatch(ExtensionPointContract $extensionPoint): ExtensionPointContract
    {
        $handlers = $this->registry->getHandlers($extensionPoint::class);

        foreach ($handlers as $handler) {
            $resolved = $this->resolveHandler($handler);
            $result = $this->callHandler($resolved, $extensionPoint);

            // Check for interruption
            if ($extensionPoint instanceof InterruptibleContract && $result === false) {
                $extensionPoint->interrupt();
                $extensionPoint->setInterruptedBy($this->getHandlerName($handler));
                break;
            }
        }

        // Dispatch as Laravel event for interop
        if ($this->dispatchAsEvents && $this->events !== null) {
            $this->events->dispatch($extensionPoint);
        }

        return $extensionPoint;
    }

    /**
     * Dispatch an interruptible extension point and return whether it was interrupted.
     *
     * Convenience method for common veto pattern.
     *
     * @return bool True if the operation can proceed, false if interrupted
     */
    public function dispatchInterruptible(InterruptibleContract $extensionPoint): bool
    {
        $this->dispatch($extensionPoint);

        return ! $extensionPoint->wasInterrupted();
    }

    /**
     * Dispatch an extension point without triggering Laravel events.
     *
     * Useful when you want to run handlers but not trigger event listeners.
     *
     * @template T of ExtensionPointContract
     *
     * @param  T  $extensionPoint
     * @return T
     */
    public function dispatchSilent(ExtensionPointContract $extensionPoint): ExtensionPointContract
    {
        $handlers = $this->registry->getHandlers($extensionPoint::class);

        foreach ($handlers as $handler) {
            $resolved = $this->resolveHandler($handler);
            $result = $this->callHandler($resolved, $extensionPoint);

            // Check for interruption
            if ($extensionPoint instanceof InterruptibleContract && $result === false) {
                $extensionPoint->interrupt();
                $extensionPoint->setInterruptedBy($this->getHandlerName($handler));
                break;
            }
        }

        return $extensionPoint;
    }

    /**
     * Check if any handlers are registered for an extension point class.
     *
     * @param  class-string<ExtensionPointContract>  $extensionPointClass
     */
    public function hasHandlers(string $extensionPointClass): bool
    {
        return $this->registry->hasHandlers($extensionPointClass);
    }

    /**
     * Resolve a handler from a class name or callable.
     *
     * @param  class-string|callable  $handler
     * @return ExtensionHandlerContract|callable
     */
    private function resolveHandler(string|callable $handler): ExtensionHandlerContract|callable
    {
        if (is_callable($handler)) {
            return $handler;
        }

        if (is_string($handler)) {
            $resolved = $this->container->make($handler);

            if (! $resolved instanceof ExtensionHandlerContract && ! is_callable($resolved)) {
                throw new InvalidArgumentException(
                    "Handler must implement ExtensionHandlerContract or be callable: {$handler}"
                );
            }

            return $resolved;
        }

        throw new InvalidArgumentException('Handler must be a class name or callable');
    }

    /**
     * Call a handler with the extension point.
     */
    private function callHandler(
        ExtensionHandlerContract|callable $handler,
        ExtensionPointContract $extensionPoint,
    ): mixed {
        if ($handler instanceof ExtensionHandlerContract) {
            return $handler->handle($extensionPoint);
        }

        if ($handler instanceof Closure) {
            return $this->container->call($handler, [
                ExtensionPointContract::class => $extensionPoint,
                $extensionPoint::class => $extensionPoint,
            ]);
        }

        return $handler($extensionPoint);
    }

    /**
     * Get a human-readable name for a handler.
     */
    private function getHandlerName(string|callable $handler): string
    {
        if (is_string($handler)) {
            return $handler;
        }

        if ($handler instanceof Closure) {
            return 'Closure';
        }

        if (is_array($handler)) {
            $class = is_object($handler[0]) ? $handler[0]::class : $handler[0];

            return "{$class}::{$handler[1]}";
        }

        if (is_object($handler)) {
            return $handler::class;
        }

        return 'callable';
    }
}
