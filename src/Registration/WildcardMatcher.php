<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Registration;

use Esegments\LaravelExtensions\Contracts\ExtensionPointContract;
use Esegments\LaravelExtensions\HandlerRegistry;

/**
 * Wildcard pattern matcher for extension points.
 *
 * Allows registering handlers that match multiple extension points
 * based on patterns.
 *
 * @example
 * ```php
 * // Listen to all events in a namespace
 * $matcher->onAny('Modules\\Orders\\Extensions\\*', AuditLogger::class);
 *
 * // Listen to pattern
 * $matcher->onAny('*Created', CreationTracker::class);
 * $matcher->onAny('Before*', PreActionLogger::class);
 * ```
 */
final class WildcardMatcher
{
    /**
     * Registered wildcard patterns.
     *
     * @var array<string, array{handler: string|callable, priority: int}>
     */
    private array $patterns = [];

    public function __construct(
        private readonly HandlerRegistry $registry,
    ) {}

    /**
     * Register a handler for extension points matching a pattern.
     *
     * @param  string  $pattern  Wildcard pattern (e.g., '*Created', 'Modules\\*\\Extensions\\*')
     * @param  string|callable  $handler
     * @return $this
     */
    public function onAny(string $pattern, string|callable $handler, int $priority = 100): self
    {
        $this->patterns[$pattern] = [
            'handler' => $handler,
            'priority' => $priority,
        ];

        return $this;
    }

    /**
     * Get handlers matching an extension point class.
     *
     * @param  class-string<ExtensionPointContract>  $extensionPointClass
     * @return array<array{handler: string|callable, priority: int}>
     */
    public function getMatchingHandlers(string $extensionPointClass): array
    {
        $matches = [];

        foreach ($this->patterns as $pattern => $handlerDef) {
            if ($this->matches($pattern, $extensionPointClass)) {
                $matches[] = $handlerDef;
            }
        }

        return $matches;
    }

    /**
     * Check if any patterns match an extension point.
     *
     * @param  class-string<ExtensionPointContract>  $extensionPointClass
     */
    public function hasMatchingPattern(string $extensionPointClass): bool
    {
        foreach ($this->patterns as $pattern => $handlerDef) {
            if ($this->matches($pattern, $extensionPointClass)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Register matched handlers with the main registry for an extension point.
     *
     * @param  class-string<ExtensionPointContract>  $extensionPointClass
     */
    public function registerMatching(string $extensionPointClass): void
    {
        foreach ($this->getMatchingHandlers($extensionPointClass) as $handlerDef) {
            $this->registry->register(
                $extensionPointClass,
                $handlerDef['handler'],
                $handlerDef['priority']
            );
        }
    }

    /**
     * Get all registered patterns.
     *
     * @return array<string>
     */
    public function getPatterns(): array
    {
        return array_keys($this->patterns);
    }

    /**
     * Remove a pattern.
     *
     * @return $this
     */
    public function removePattern(string $pattern): self
    {
        unset($this->patterns[$pattern]);

        return $this;
    }

    /**
     * Clear all patterns.
     *
     * @return $this
     */
    public function clear(): self
    {
        $this->patterns = [];

        return $this;
    }

    /**
     * Check if a pattern matches an extension point class.
     */
    private function matches(string $pattern, string $extensionPointClass): bool
    {
        // Convert wildcard pattern to regex
        $regex = $this->patternToRegex($pattern);

        return (bool) preg_match($regex, $extensionPointClass);
    }

    /**
     * Convert a wildcard pattern to a regex.
     */
    private function patternToRegex(string $pattern): string
    {
        // Escape regex special characters except *
        $escaped = preg_quote($pattern, '/');

        // Convert * to regex equivalent (match any characters except backslash)
        $regex = str_replace('\\*', '[^\\\\]*', $escaped);

        // Handle ** for recursive namespace matching
        $regex = str_replace('[^\\\\]*[^\\\\]*', '.*', $regex);

        return '/^' . $regex . '$/';
    }
}
