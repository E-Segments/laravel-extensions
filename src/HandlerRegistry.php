<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions;

use Esegments\LaravelExtensions\Contracts\ExtensionPointContract;
use InvalidArgumentException;

/**
 * Registry for extension point handlers.
 *
 * Handlers are registered with a priority (lower = runs first).
 * This class is Octane-safe as it's registered as a singleton in the container.
 *
 * Priority ranges:
 * - 0-49: Critical (veto checks, security)
 * - 50-99: High (cache invalidation)
 * - 100-149: Normal (default: 100)
 * - 150-199: Low (notifications)
 * - 200+: Very low (analytics)
 */
final class HandlerRegistry
{
    /**
     * Registered handlers indexed by extension point class.
     *
     * @var array<class-string<ExtensionPointContract>, array<array{handler: string|callable, priority: int}>>
     */
    private array $handlers = [];

    /**
     * Cached sorted handlers to avoid re-sorting on every dispatch.
     *
     * @var array<class-string<ExtensionPointContract>, array<string|callable>>
     */
    private array $sortedCache = [];

    /**
     * Register a handler for an extension point.
     *
     * @param  class-string<ExtensionPointContract>  $extensionPointClass
     * @param  class-string|callable  $handler  Handler class or callable
     * @param  int  $priority  Lower values run first (default: 100)
     *
     * @throws InvalidArgumentException If extension point class is invalid
     */
    public function register(
        string $extensionPointClass,
        string|callable $handler,
        int $priority = 100,
    ): self {
        if (! is_subclass_of($extensionPointClass, ExtensionPointContract::class)) {
            throw new InvalidArgumentException(
                "Extension point class must implement ExtensionPointContract: {$extensionPointClass}"
            );
        }

        $this->handlers[$extensionPointClass][] = [
            'handler' => $handler,
            'priority' => $priority,
        ];

        // Invalidate cache for this extension point
        unset($this->sortedCache[$extensionPointClass]);

        return $this;
    }

    /**
     * Get all handlers for an extension point, sorted by priority.
     *
     * @param  class-string<ExtensionPointContract>  $extensionPointClass
     * @return array<string|callable>
     */
    public function getHandlers(string $extensionPointClass): array
    {
        if (! isset($this->handlers[$extensionPointClass])) {
            return [];
        }

        // Return cached sorted handlers if available
        if (isset($this->sortedCache[$extensionPointClass])) {
            return $this->sortedCache[$extensionPointClass];
        }

        // Sort by priority (lower values first)
        $handlers = $this->handlers[$extensionPointClass];
        usort($handlers, fn (array $a, array $b) => $a['priority'] <=> $b['priority']);

        // Extract just the handler references and cache
        $sorted = array_map(fn (array $item) => $item['handler'], $handlers);
        $this->sortedCache[$extensionPointClass] = $sorted;

        return $sorted;
    }

    /**
     * Check if any handlers are registered for an extension point.
     *
     * @param  class-string<ExtensionPointContract>  $extensionPointClass
     */
    public function hasHandlers(string $extensionPointClass): bool
    {
        return ! empty($this->handlers[$extensionPointClass]);
    }

    /**
     * Get all handlers with their priorities for an extension point.
     *
     * Useful for debugging or displaying registered handlers.
     *
     * @param  class-string<ExtensionPointContract>  $extensionPointClass
     * @return array<array{handler: string|callable, priority: int}>
     */
    public function getHandlersWithPriorities(string $extensionPointClass): array
    {
        if (! isset($this->handlers[$extensionPointClass])) {
            return [];
        }

        $handlers = $this->handlers[$extensionPointClass];
        usort($handlers, fn (array $a, array $b) => $a['priority'] <=> $b['priority']);

        return $handlers;
    }

    /**
     * Remove all handlers for a specific extension point.
     *
     * @param  class-string<ExtensionPointContract>  $extensionPointClass
     */
    public function forget(string $extensionPointClass): self
    {
        unset($this->handlers[$extensionPointClass]);
        unset($this->sortedCache[$extensionPointClass]);

        return $this;
    }

    /**
     * Clear all registered handlers.
     *
     * Primarily useful for testing.
     */
    public function clear(): self
    {
        $this->handlers = [];
        $this->sortedCache = [];

        return $this;
    }

    /**
     * Get all registered extension point classes.
     *
     * @return array<class-string<ExtensionPointContract>>
     */
    public function getRegisteredExtensionPoints(): array
    {
        return array_keys($this->handlers);
    }

    /**
     * Get the count of handlers for an extension point.
     *
     * @param  class-string<ExtensionPointContract>  $extensionPointClass
     */
    public function countHandlers(string $extensionPointClass): int
    {
        return count($this->handlers[$extensionPointClass] ?? []);
    }
}
