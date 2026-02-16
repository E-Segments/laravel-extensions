<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions;

use Closure;
use Esegments\Core\Concerns\Makeable;
use Esegments\LaravelExtensions\Contracts\ExtensionPointContract;
use Esegments\LaravelExtensions\Exceptions\ExtensionException;
use Esegments\LaravelExtensions\Registration\ConditionalRegistration;
use Illuminate\Support\Collection;

/**
 * Registry for extension point handlers.
 *
 * Handlers are registered with a priority (lower numbers run first).
 * The registry maintains a sorted cache for efficient dispatching.
 *
 * Priority ranges:
 * - 0-49: Critical (veto checks, security)
 * - 50-99: High (cache invalidation)
 * - 100-149: Normal (default: 100)
 * - 150-199: Low (notifications)
 * - 200+: Very low (analytics)
 *
 * @example
 * ```php
 * $registry = HandlerRegistry::make();
 *
 * // Register class-based handler
 * $registry->register(
 *     ValidateOrderExtension::class,
 *     CheckInventoryHandler::class,
 *     priority: 10,
 * );
 *
 * // Register closure handler
 * $registry->register(
 *     ValidateOrderExtension::class,
 *     fn (ValidateOrderExtension $ext) => $ext->addError('Error'),
 *     priority: 20,
 * );
 *
 * // Register with tags
 * $registry->register(UserCreated::class, EmailHandler::class)
 *     ->tag(['notifications', 'email']);
 * ```
 */
final class HandlerRegistry
{
    use Makeable;

    /**
     * Registered handlers indexed by extension point class.
     *
     * @var array<class-string<ExtensionPointContract>, array<int, array{handler: string|callable, priority: int, tags: array<string>}>>
     */
    private array $handlers = [];

    /**
     * Cache of sorted handlers by extension point class.
     *
     * @var array<class-string<ExtensionPointContract>, array<array{handler: string|callable, priority: int, tags: array<string>}>>
     */
    private array $sortedCache = [];

    /**
     * Handler groups for bulk operations.
     *
     * @var array<string, array<array{extension: class-string<ExtensionPointContract>, handler: string|callable, priority: int}>>
     */
    private array $groups = [];

    /**
     * Disabled groups.
     *
     * @var array<string, bool>
     */
    private array $disabledGroups = [];

    /**
     * Tags index for quick lookup.
     *
     * @var array<string, array<array{extension: class-string<ExtensionPointContract>, handler: string|callable}>>
     */
    private array $tags = [];

    /**
     * Disabled tags.
     *
     * @var array<string, bool>
     */
    private array $disabledTags = [];

    /**
     * Last registered handler info (for fluent tagging).
     *
     * @var array{extension: class-string<ExtensionPointContract>, handler: string|callable}|null
     */
    private ?array $lastRegistered = null;

    /**
     * Register a handler for an extension point.
     *
     * @param  class-string<ExtensionPointContract>  $extensionPointClass
     * @param  string|callable  $handler  Class name or callable
     * @param  int  $priority  Lower numbers run first (default: 100)
     * @return $this
     */
    public function register(
        string $extensionPointClass,
        string|callable $handler,
        int $priority = 100,
    ): self {
        if (! is_a($extensionPointClass, ExtensionPointContract::class, true)) {
            throw ExtensionException::invalidExtensionPoint($extensionPointClass);
        }

        $this->handlers[$extensionPointClass][] = [
            'handler' => $handler,
            'priority' => $priority,
            'tags' => [],
        ];

        // Track for fluent tagging
        $this->lastRegistered = [
            'extension' => $extensionPointClass,
            'handler' => $handler,
        ];

        // Invalidate sorted cache
        unset($this->sortedCache[$extensionPointClass]);

        return $this;
    }

    /**
     * Tag the last registered handler.
     *
     * @param  array<string>  $tags
     * @return $this
     */
    public function tag(array $tags): self
    {
        if ($this->lastRegistered === null) {
            return $this;
        }

        $extension = $this->lastRegistered['extension'];
        $handler = $this->lastRegistered['handler'];

        // Find and update the handler entry
        foreach ($this->handlers[$extension] as $index => $handlerDef) {
            if ($handlerDef['handler'] === $handler) {
                $this->handlers[$extension][$index]['tags'] = array_merge(
                    $this->handlers[$extension][$index]['tags'],
                    $tags
                );
                break;
            }
        }

        // Update tags index
        foreach ($tags as $tag) {
            $this->tags[$tag][] = [
                'extension' => $extension,
                'handler' => $handler,
            ];
        }

        // Invalidate sorted cache
        unset($this->sortedCache[$extension]);

        return $this;
    }

    /**
     * Get handlers by tag.
     *
     * @return Collection<int, array{extension: class-string<ExtensionPointContract>, handler: string|callable}>
     */
    public function tagged(string $tag): Collection
    {
        return collect($this->tags[$tag] ?? []);
    }

    /**
     * Disable all handlers with a specific tag.
     *
     * @return $this
     */
    public function disableTag(string $tag): self
    {
        $this->disabledTags[$tag] = true;

        // Remove handlers with this tag
        foreach ($this->tags[$tag] ?? [] as $handlerDef) {
            $this->removeHandler($handlerDef['extension'], $handlerDef['handler']);
        }

        return $this;
    }

    /**
     * Enable all handlers with a specific tag.
     *
     * @return $this
     */
    public function enableTag(string $tag): self
    {
        unset($this->disabledTags[$tag]);

        // Re-register handlers with this tag
        foreach ($this->tags[$tag] ?? [] as $handlerDef) {
            // Find priority from stored handlers
            foreach ($this->handlers[$handlerDef['extension']] ?? [] as $stored) {
                if ($stored['handler'] === $handlerDef['handler']) {
                    $this->register($handlerDef['extension'], $handlerDef['handler'], $stored['priority']);
                    break;
                }
            }
        }

        return $this;
    }

    /**
     * Check if a tag is disabled.
     */
    public function isTagDisabled(string $tag): bool
    {
        return $this->disabledTags[$tag] ?? false;
    }

    /**
     * Get all registered tags.
     *
     * @return array<string>
     */
    public function getRegisteredTags(): array
    {
        return array_keys($this->tags);
    }

    /**
     * Conditional registration.
     *
     * @param  bool|Closure(): bool  $condition
     */
    public function when(bool|Closure $condition): ConditionalRegistration
    {
        return new ConditionalRegistration($this, $condition);
    }

    /**
     * Register a group of handlers for bulk operations.
     *
     * @param  array<array{0: class-string<ExtensionPointContract>, 1: string|callable, 2?: int}>  $handlers
     * @return $this
     */
    public function registerGroup(string $groupName, array $handlers): self
    {
        foreach ($handlers as $handlerDef) {
            $extension = $handlerDef[0];
            $handler = $handlerDef[1];
            $priority = $handlerDef[2] ?? 100;

            $this->groups[$groupName][] = [
                'extension' => $extension,
                'handler' => $handler,
                'priority' => $priority,
            ];

            $this->register($extension, $handler, $priority);
        }

        return $this;
    }

    /**
     * Disable a handler group.
     *
     * @return $this
     */
    public function disableGroup(string $groupName): self
    {
        $this->disabledGroups[$groupName] = true;

        // Remove handlers from disabled group
        if (isset($this->groups[$groupName])) {
            foreach ($this->groups[$groupName] as $handlerDef) {
                $this->removeHandler(
                    $handlerDef['extension'],
                    $handlerDef['handler']
                );
            }
        }

        return $this;
    }

    /**
     * Enable a previously disabled handler group.
     *
     * @return $this
     */
    public function enableGroup(string $groupName): self
    {
        unset($this->disabledGroups[$groupName]);

        // Re-register handlers from enabled group
        if (isset($this->groups[$groupName])) {
            foreach ($this->groups[$groupName] as $handlerDef) {
                $this->register(
                    $handlerDef['extension'],
                    $handlerDef['handler'],
                    $handlerDef['priority']
                );
            }
        }

        return $this;
    }

    /**
     * Check if a group is disabled.
     */
    public function isGroupDisabled(string $groupName): bool
    {
        return $this->disabledGroups[$groupName] ?? false;
    }

    /**
     * Remove a specific handler from an extension point.
     *
     * @param  class-string<ExtensionPointContract>  $extensionPointClass
     * @return $this
     */
    public function removeHandler(string $extensionPointClass, string|callable $handler): self
    {
        if (! isset($this->handlers[$extensionPointClass])) {
            return $this;
        }

        $this->handlers[$extensionPointClass] = array_values(
            array_filter(
                $this->handlers[$extensionPointClass],
                fn (array $item) => $item['handler'] !== $handler
            )
        );

        // Invalidate sorted cache
        unset($this->sortedCache[$extensionPointClass]);

        return $this;
    }

    /**
     * Get all handlers for an extension point, sorted by priority.
     *
     * @param  class-string<ExtensionPointContract>  $extensionPointClass
     * @return array<array{handler: string|callable, priority: int, tags: array<string>}>
     */
    public function getHandlers(string $extensionPointClass): array
    {
        if (! isset($this->handlers[$extensionPointClass])) {
            return [];
        }

        if (! isset($this->sortedCache[$extensionPointClass])) {
            $handlers = $this->handlers[$extensionPointClass];
            usort($handlers, fn (array $a, array $b) => $a['priority'] <=> $b['priority']);
            $this->sortedCache[$extensionPointClass] = $handlers;
        }

        return $this->sortedCache[$extensionPointClass];
    }

    /**
     * Check if there are any handlers registered for an extension point.
     *
     * @param  class-string<ExtensionPointContract>  $extensionPointClass
     */
    public function hasHandlers(string $extensionPointClass): bool
    {
        return ! empty($this->handlers[$extensionPointClass]);
    }

    /**
     * Get the number of handlers registered for an extension point.
     *
     * @param  class-string<ExtensionPointContract>  $extensionPointClass
     */
    public function countHandlers(string $extensionPointClass): int
    {
        return count($this->handlers[$extensionPointClass] ?? []);
    }

    /**
     * Forget all handlers for an extension point.
     *
     * @param  class-string<ExtensionPointContract>  $extensionPointClass
     * @return $this
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
     * @return $this
     */
    public function clear(): self
    {
        $this->handlers = [];
        $this->sortedCache = [];
        $this->groups = [];
        $this->disabledGroups = [];
        $this->tags = [];
        $this->disabledTags = [];
        $this->lastRegistered = null;

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
     * Get all registered groups.
     *
     * @return array<string>
     */
    public function getRegisteredGroups(): array
    {
        return array_keys($this->groups);
    }
}
