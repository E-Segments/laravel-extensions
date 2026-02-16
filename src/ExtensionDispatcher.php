<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions;

use Esegments\LaravelExtensions\CircuitBreaker\CircuitBreaker;
use Esegments\LaravelExtensions\Concerns\GracefulExecution;
use Esegments\LaravelExtensions\Concerns\Mutable;
use Esegments\LaravelExtensions\Concerns\Silenceable;
use Esegments\LaravelExtensions\Contracts\AsyncHandlerContract;
use Esegments\LaravelExtensions\Contracts\ExtensionHandlerContract;
use Esegments\LaravelExtensions\Contracts\ExtensionPointContract;
use Esegments\LaravelExtensions\Contracts\InterruptibleContract;
use Esegments\LaravelExtensions\Exceptions\StrictModeException;
use Esegments\LaravelExtensions\Jobs\DispatchAsyncHandler;
use Esegments\LaravelExtensions\Logging\ExtensionLogger;
use Esegments\LaravelExtensions\Results\DispatchResult;
use Esegments\LaravelExtensions\Support\DebugInfo;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use Throwable;

/**
 * Dispatches extension points to their registered handlers.
 *
 * The dispatcher resolves handlers from the container and executes them
 * in priority order. For InterruptibleContract extension points, handlers
 * can return `false` to stop processing.
 *
 * Features:
 * - Priority-based handler execution
 * - Interruptible (veto-able) extension points
 * - Debug logging for development
 * - Async handler support via queues
 * - Laravel event integration
 * - Graceful error handling mode
 * - Circuit breaker pattern
 * - Handler muting and silencing
 *
 * @example
 * ```php
 * // Simple dispatch (returns the extension point)
 * $extension = $dispatcher->dispatch(new MyExtension($data));
 *
 * // Interruptible dispatch (returns bool indicating if processing completed)
 * $canProceed = $dispatcher->dispatchInterruptible(new ValidateExtension($data));
 * if (! $canProceed) {
 *     // Handle interruption
 * }
 *
 * // Graceful dispatch (continues even if handlers fail)
 * $result = $dispatcher->gracefully()->dispatchWithResults(new MyExtension($data));
 * ```
 */
final class ExtensionDispatcher
{
    use GracefulExecution;
    use Mutable;
    use Silenceable;

    private ?ExtensionLogger $logger = null;

    public function __construct(
        private readonly Container $container,
        private readonly HandlerRegistry $registry,
        private readonly ?EventDispatcher $events = null,
        private readonly bool $debug = false,
        private readonly ?string $logChannel = null,
        private readonly bool $strictMode = false,
        private readonly ?CircuitBreaker $circuitBreaker = null,
    ) {
        if ($this->debug) {
            $this->logger = new ExtensionLogger($this->logChannel);
        }

        // Initialize graceful mode from config
        $this->gracefulMode = (bool) config('extensions.graceful_mode', false);
    }

    /**
     * Get the circuit breaker instance.
     */
    public function circuitBreaker(): ?CircuitBreaker
    {
        return $this->circuitBreaker;
    }

    /**
     * Dispatch an extension point to all registered handlers.
     *
     * @template T of ExtensionPointContract
     *
     * @param  T  $extensionPoint
     * @return T
     */
    public function dispatch(ExtensionPointContract $extensionPoint): ExtensionPointContract
    {
        // Check if silenced
        if ($this->isSilenced()) {
            return $extensionPoint;
        }

        // Check strict mode
        $this->checkStrictMode($extensionPoint);

        $debugInfo = $this->debug ? new DebugInfo($extensionPoint::class, microtime(true)) : null;

        $this->processHandlers($extensionPoint, $debugInfo);

        if ($debugInfo !== null) {
            $debugInfo->complete($extensionPoint);
            $this->logger?->logDispatch($debugInfo);
        }

        // Also dispatch as Laravel event for interop
        $this->events?->dispatch($extensionPoint);

        // Reset graceful mode after dispatch
        $this->resetGracefulMode();

        return $extensionPoint;
    }

    /**
     * Dispatch an interruptible extension point.
     *
     * Returns `true` if all handlers completed successfully, `false` if any handler
     * returned `false` to interrupt processing.
     *
     * @return bool True if processing completed, false if interrupted
     */
    public function dispatchInterruptible(InterruptibleContract $extensionPoint): bool
    {
        // Check if silenced
        if ($this->isSilenced()) {
            return true;
        }

        // Check strict mode
        $this->checkStrictMode($extensionPoint);

        $debugInfo = $this->debug ? new DebugInfo($extensionPoint::class, microtime(true)) : null;

        $completed = $this->processHandlers($extensionPoint, $debugInfo);

        if ($debugInfo !== null) {
            $debugInfo->complete($extensionPoint);
            $this->logger?->logDispatch($debugInfo);
        }

        // Also dispatch as Laravel event for interop
        $this->events?->dispatch($extensionPoint);

        // Reset graceful mode after dispatch
        $this->resetGracefulMode();

        return $completed && ! $extensionPoint->wasInterrupted();
    }

    /**
     * Dispatch an extension point and collect results from all handlers.
     *
     * @template T of ExtensionPointContract
     *
     * @param  T  $extensionPoint
     * @return DispatchResult<T>
     */
    public function dispatchWithResults(ExtensionPointContract $extensionPoint): DispatchResult
    {
        // Check if silenced
        if ($this->isSilenced()) {
            return new DispatchResult($extensionPoint);
        }

        $debugInfo = $this->debug ? new DebugInfo($extensionPoint::class, microtime(true)) : null;
        $handlers = $this->registry->getHandlers($extensionPoint::class);

        $wasInterrupted = false;
        $interruptedBy = null;

        $result = new DispatchResult($extensionPoint, $debugInfo);

        foreach ($handlers as $handlerDef) {
            $handlerClass = $this->getHandlerClass($handlerDef['handler']);
            $startTime = microtime(true);

            // Check if handler is muted
            if ($this->isMuted($handlerClass)) {
                $result->recordSkipped($handlerClass, 'muted');
                $debugInfo?->recordHandler($handlerClass, 0, '[muted]');

                continue;
            }

            // Check circuit breaker
            if ($this->circuitBreaker && ! $this->circuitBreaker->isAvailable($handlerClass)) {
                $result->recordSkipped($handlerClass, 'circuit_open');
                $debugInfo?->recordHandler($handlerClass, 0, '[circuit_open]');

                continue;
            }

            // Check if handler should run async
            if ($this->shouldRunAsync($handlerDef['handler'])) {
                $this->dispatchAsync($handlerClass, $extensionPoint);
                $result->recordSkipped($handlerClass, 'async:queued');
                $debugInfo?->recordHandler($handlerClass, 0, '[async:queued]');

                continue;
            }

            try {
                $handler = $this->resolveHandler($handlerDef['handler']);
                $handlerResult = $handler($extensionPoint);
                $result->recordSuccess($handlerClass, $handlerResult);

                // Record success with circuit breaker
                $this->circuitBreaker?->recordSuccess($handlerClass);

                // Check for interruption
                if ($extensionPoint instanceof InterruptibleContract && $handlerResult === false) {
                    $extensionPoint->interrupt();
                    $extensionPoint->setInterruptedBy($handlerClass);
                    $wasInterrupted = true;
                    $interruptedBy = $handlerClass;

                    $debugInfo?->recordHandler($handlerClass, (microtime(true) - $startTime) * 1000, $handlerResult);
                    break;
                }

                $debugInfo?->recordHandler($handlerClass, (microtime(true) - $startTime) * 1000, $handlerResult);
            } catch (Throwable $e) {
                $result->recordError($handlerClass, $e);

                // Record failure with circuit breaker
                $this->circuitBreaker?->recordFailure($handlerClass);

                $debugInfo?->recordHandler($handlerClass, (microtime(true) - $startTime) * 1000, null, $e->getMessage());
                $this->logger?->logHandlerError($extensionPoint::class, $handlerClass, $e);

                // If not in graceful mode, re-throw the exception
                if (! $this->isGracefulMode()) {
                    throw $e;
                }
            }
        }

        if ($debugInfo !== null) {
            $debugInfo->complete($extensionPoint);
            $this->logger?->logDispatch($debugInfo);
        }

        // Also dispatch as Laravel event for interop
        $this->events?->dispatch($extensionPoint);

        // Reset graceful mode after dispatch
        $this->resetGracefulMode();

        return $result;
    }

    /**
     * Dispatch an extension point without firing a Laravel event.
     *
     * Use this when you want to process handlers but skip the Laravel event
     * integration (e.g., for performance or to avoid recursive event handling).
     *
     * @template T of ExtensionPointContract
     *
     * @param  T  $extensionPoint
     * @return T
     */
    public function dispatchSilent(ExtensionPointContract $extensionPoint): ExtensionPointContract
    {
        // Check if silenced
        if ($this->isSilenced()) {
            return $extensionPoint;
        }

        $debugInfo = $this->debug ? new DebugInfo($extensionPoint::class, microtime(true)) : null;

        $this->processHandlers($extensionPoint, $debugInfo);

        if ($debugInfo !== null) {
            $debugInfo->complete($extensionPoint);
            $this->logger?->logDispatch($debugInfo);
        }

        // Reset graceful mode after dispatch
        $this->resetGracefulMode();

        return $extensionPoint;
    }

    /**
     * Dispatch an interruptible extension point without firing a Laravel event.
     *
     * @return bool True if processing completed, false if interrupted
     */
    public function dispatchInterruptibleSilent(InterruptibleContract $extensionPoint): bool
    {
        // Check if silenced
        if ($this->isSilenced()) {
            return true;
        }

        $debugInfo = $this->debug ? new DebugInfo($extensionPoint::class, microtime(true)) : null;

        $completed = $this->processHandlers($extensionPoint, $debugInfo);

        if ($debugInfo !== null) {
            $debugInfo->complete($extensionPoint);
            $this->logger?->logDispatch($debugInfo);
        }

        // Reset graceful mode after dispatch
        $this->resetGracefulMode();

        return $completed && ! $extensionPoint->wasInterrupted();
    }

    /**
     * Check if any handlers are registered for an extension point.
     *
     * @param  class-string<ExtensionPointContract>  $extensionPointClass
     */
    public function hasHandlers(string $extensionPointClass): bool
    {
        return $this->registry->hasHandlers($extensionPointClass);
    }

    /**
     * Get debug info for the last dispatch (if debug mode is enabled).
     */
    public function isDebugEnabled(): bool
    {
        return $this->debug;
    }

    /**
     * Check if strict mode is enabled.
     */
    public function isStrictModeEnabled(): bool
    {
        return $this->strictMode;
    }

    /**
     * Check strict mode and throw if no handlers are registered.
     *
     * @throws StrictModeException
     */
    private function checkStrictMode(ExtensionPointContract $extensionPoint): void
    {
        if (! $this->strictMode) {
            return;
        }

        if (! $this->registry->hasHandlers($extensionPoint::class)) {
            throw StrictModeException::unregisteredExtensionPoint($extensionPoint::class);
        }
    }

    /**
     * Process all handlers for an extension point.
     *
     * @return bool True if all handlers completed, false if interrupted
     */
    private function processHandlers(ExtensionPointContract $extensionPoint, ?DebugInfo $debugInfo): bool
    {
        $handlers = $this->registry->getHandlers($extensionPoint::class);

        foreach ($handlers as $handlerDef) {
            $handlerClass = $this->getHandlerClass($handlerDef['handler']);
            $startTime = microtime(true);

            // Check if handler is muted
            if ($this->isMuted($handlerClass)) {
                $debugInfo?->recordHandler($handlerClass, 0, '[muted]');

                continue;
            }

            // Check circuit breaker
            if ($this->circuitBreaker && ! $this->circuitBreaker->isAvailable($handlerClass)) {
                $debugInfo?->recordHandler($handlerClass, 0, '[circuit_open]');

                continue;
            }

            // Check if handler should run async
            if ($this->shouldRunAsync($handlerDef['handler'])) {
                $this->dispatchAsync($handlerClass, $extensionPoint);
                $debugInfo?->recordHandler($handlerClass, 0, '[async:queued]');

                continue;
            }

            try {
                $handler = $this->resolveHandler($handlerDef['handler']);
                $result = $handler($extensionPoint);

                // Record success with circuit breaker
                $this->circuitBreaker?->recordSuccess($handlerClass);

                $debugInfo?->recordHandler($handlerClass, (microtime(true) - $startTime) * 1000, $result);

                // Check for interruption (only for InterruptibleContract)
                if ($extensionPoint instanceof InterruptibleContract && $result === false) {
                    $extensionPoint->interrupt();
                    $extensionPoint->setInterruptedBy($handlerClass);

                    return false;
                }
            } catch (Throwable $e) {
                // Record failure with circuit breaker
                $this->circuitBreaker?->recordFailure($handlerClass);

                $debugInfo?->recordHandler($handlerClass, (microtime(true) - $startTime) * 1000, null, $e->getMessage());
                $this->logger?->logHandlerError($extensionPoint::class, $handlerClass, $e);

                // In graceful mode, continue to next handler
                if ($this->isGracefulMode()) {
                    continue;
                }

                throw $e;
            }
        }

        return true;
    }

    /**
     * Check if a handler should run asynchronously.
     */
    private function shouldRunAsync(string|callable $handler): bool
    {
        if (! is_string($handler)) {
            return false;
        }

        if (! class_exists($handler)) {
            return false;
        }

        return is_a($handler, AsyncHandlerContract::class, true);
    }

    /**
     * Dispatch a handler asynchronously via queue.
     */
    private function dispatchAsync(string $handlerClass, ExtensionPointContract $extensionPoint): void
    {
        $queue = config('extensions.async.default_queue', 'default');

        DispatchAsyncHandler::dispatch($handlerClass, $extensionPoint, $queue);
    }

    /**
     * Resolve a handler to a callable.
     */
    private function resolveHandler(string|callable $handler): callable
    {
        if (is_callable($handler)) {
            return $handler;
        }

        // Resolve class from container
        $instance = $this->container->make($handler);

        if ($instance instanceof ExtensionHandlerContract) {
            return fn (ExtensionPointContract $ext) => $instance->handle($ext);
        }

        // Support invokable classes
        if (is_callable($instance)) {
            return $instance;
        }

        throw new \InvalidArgumentException(
            "Handler [{$handler}] must implement " . ExtensionHandlerContract::class . ' or be invokable'
        );
    }

    /**
     * Get the class name for a handler.
     */
    private function getHandlerClass(string|callable $handler): string
    {
        if (is_string($handler)) {
            return $handler;
        }

        if (is_object($handler) && ! $handler instanceof \Closure) {
            return $handler::class;
        }

        return 'Closure';
    }
}
