<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Facades;

use Esegments\LaravelExtensions\CircuitBreaker\CircuitBreaker;
use Esegments\LaravelExtensions\Contracts\ExtensionPointContract;
use Esegments\LaravelExtensions\Contracts\InterruptibleContract;
use Esegments\LaravelExtensions\ExtensionDispatcher;
use Esegments\LaravelExtensions\HandlerRegistry;
use Esegments\LaravelExtensions\Jobs\BatchDispatchJob;
use Esegments\LaravelExtensions\Pipeline\ExtensionPipeline;
use Esegments\LaravelExtensions\Registration\ConditionalRegistration;
use Esegments\LaravelExtensions\Results\DispatchResult;
use Esegments\LaravelExtensions\Scoping\ScopedRegistry;
use Esegments\LaravelExtensions\Strategies\FirstResultStrategy;
use Esegments\LaravelExtensions\Strategies\MergeResultsStrategy;
use Esegments\LaravelExtensions\Strategies\ReduceResultsStrategy;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Facade;

/**
 * Facade for the ExtensionDispatcher.
 *
 * Core Dispatch Methods:
 * @method static ExtensionPointContract dispatch(ExtensionPointContract $extensionPoint)
 * @method static bool dispatchInterruptible(InterruptibleContract $extensionPoint)
 * @method static ExtensionPointContract dispatchSilent(ExtensionPointContract $extensionPoint)
 * @method static bool dispatchInterruptibleSilent(InterruptibleContract $extensionPoint)
 * @method static DispatchResult dispatchWithResults(ExtensionPointContract $extensionPoint)
 * @method static bool hasHandlers(string $extensionPointClass)
 *
 * Debug & Configuration:
 * @method static bool isDebugEnabled()
 * @method static bool isStrictModeEnabled()
 * @method static CircuitBreaker|null circuitBreaker()
 *
 * Graceful Mode (Phase 1):
 * @method static ExtensionDispatcher gracefully()
 * @method static ExtensionDispatcher strictly()
 * @method static bool isGracefulMode()
 * @method static ExtensionDispatcher resetGracefulMode()
 *
 * Muting (Phase 1):
 * @method static ExtensionDispatcher mute(string $handlerClass)
 * @method static ExtensionDispatcher unmute(string $handlerClass)
 * @method static bool isMuted(string $handlerClass)
 * @method static array getMutedHandlers()
 * @method static ExtensionDispatcher clearMuted()
 * @method static mixed withMuted(string $handlerClass, callable $callback)
 * @method static mixed withMutedMany(array $handlerClasses, callable $callback)
 *
 * Silencing (Phase 1):
 * @method static mixed silence(callable $callback)
 * @method static ExtensionDispatcher silenceAll()
 * @method static ExtensionDispatcher resumeAll()
 * @method static bool isSilenced()
 *
 * @see ExtensionDispatcher
 */
class Extensions extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ExtensionDispatcher::class;
    }

    /**
     * Create a conditional registration builder.
     *
     * @param  bool|callable  $condition
     */
    public static function when(bool|callable $condition): ConditionalRegistration
    {
        return new ConditionalRegistration(
            app(HandlerRegistry::class),
            $condition,
        );
    }

    /**
     * Create a pipeline for an extension point.
     */
    public static function pipeline(ExtensionPointContract $extensionPoint): ExtensionPipeline
    {
        return ExtensionPipeline::for($extensionPoint);
    }

    /**
     * Get the scoped registry for request/tenant scoping.
     */
    public static function scoped(): ScopedRegistry
    {
        return app(ScopedRegistry::class);
    }

    /**
     * Create a scoped handler registration for the current request.
     */
    public static function forRequest(): ScopedRegistry
    {
        return app(ScopedRegistry::class)->forRequest();
    }

    /**
     * Create a scoped handler registration for a tenant.
     */
    public static function forTenant(string|int $tenantId): ScopedRegistry
    {
        return app(ScopedRegistry::class)->forTenant($tenantId);
    }

    /**
     * Create a scoped handler registration with a custom scope.
     */
    public static function scope(string $scope): ScopedRegistry
    {
        return app(ScopedRegistry::class)->scope($scope);
    }

    /**
     * Clear a custom scope.
     */
    public static function clearScope(string $scope): void
    {
        app(ScopedRegistry::class)->clearScope($scope);
    }

    /**
     * Create a first-result strategy dispatcher.
     */
    public static function firstResult(): FirstResultStrategy
    {
        return app(FirstResultStrategy::class);
    }

    /**
     * Create a merge-results strategy dispatcher.
     */
    public static function mergeResults(bool $asCollection = true, bool $flattenArrays = true): MergeResultsStrategy
    {
        return new MergeResultsStrategy($asCollection, $flattenArrays);
    }

    /**
     * Create a reduce-results strategy dispatcher.
     *
     * @param  callable  $reducer  Function with signature: ($carry, $result) => mixed
     * @param  mixed  $initial  Initial value for reduction
     */
    public static function reduceResults(callable $reducer, mixed $initial = null): ReduceResultsStrategy
    {
        return new ReduceResultsStrategy($reducer, $initial);
    }

    /**
     * Create a batch for multiple extension points.
     *
     * @param  array<ExtensionPointContract>  $extensionPoints
     */
    public static function batch(array $extensionPoints): PendingBatch
    {
        return BatchDispatchJob::createBatch($extensionPoints);
    }

    /**
     * Register a handler with tags.
     *
     * @param  class-string<ExtensionPointContract>  $extensionPointClass
     * @param  class-string  $handlerClass
     * @param  array<string>  $tags
     */
    public static function registerWithTags(
        string $extensionPointClass,
        string $handlerClass,
        int $priority = 0,
        array $tags = [],
    ): void {
        $registry = app(HandlerRegistry::class);
        $registry->register($extensionPointClass, $handlerClass, $priority);

        if (! empty($tags)) {
            $registry->tag($handlerClass, $tags);
        }
    }

    /**
     * Disable all handlers with a specific tag.
     */
    public static function disableTag(string $tag): void
    {
        app(HandlerRegistry::class)->disableTag($tag);
    }

    /**
     * Enable all handlers with a specific tag.
     */
    public static function enableTag(string $tag): void
    {
        app(HandlerRegistry::class)->enableTag($tag);
    }

    /**
     * Get handlers by tag.
     *
     * @return array<string>
     */
    public static function tagged(string $tag): array
    {
        return app(HandlerRegistry::class)->tagged($tag);
    }

    /**
     * Register a wildcard handler.
     *
     * @param  class-string  $handlerClass
     */
    public static function onAny(string $pattern, string $handlerClass, int $priority = 0): void
    {
        app(\Esegments\LaravelExtensions\Registration\WildcardMatcher::class)
            ->register($pattern, $handlerClass, $priority);
    }
}
