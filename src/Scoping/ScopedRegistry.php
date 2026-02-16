<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Scoping;

use Esegments\LaravelExtensions\Contracts\ExtensionPointContract;
use Esegments\LaravelExtensions\HandlerRegistry;

/**
 * Registry for scoped handlers.
 *
 * Scoped handlers are only active within a specific context (request, tenant, etc.)
 * and are automatically cleaned up when the scope ends.
 *
 * @example
 * ```php
 * // Register a handler for the current request only
 * $scopedRegistry->forRequest()->register(PageViewed::class, SessionTracker::class);
 *
 * // Register a handler for a specific tenant
 * $scopedRegistry->forTenant($tenantId)->register(OrderPlaced::class, TenantHandler::class);
 *
 * // Custom scope
 * $scopedRegistry->scope('import-job-123')->register(ProductCreated::class, ImportLogger::class);
 * ```
 */
final class ScopedRegistry
{
    /**
     * @var array<string, array<array{extension: class-string<ExtensionPointContract>, handler: string|callable, priority: int}>>
     */
    private array $scopes = [];

    /**
     * Current active scope.
     */
    private ?string $activeScope = null;

    public function __construct(
        private readonly HandlerRegistry $registry,
    ) {}

    /**
     * Create a request-scoped registration context.
     */
    public function forRequest(): self
    {
        return $this->scope('request:' . request()?->fingerprint() ?? 'console');
    }

    /**
     * Create a tenant-scoped registration context.
     */
    public function forTenant(int|string $tenantId): self
    {
        return $this->scope('tenant:' . $tenantId);
    }

    /**
     * Create a user-scoped registration context.
     */
    public function forUser(int|string $userId): self
    {
        return $this->scope('user:' . $userId);
    }

    /**
     * Create a custom scope.
     */
    public function scope(string $scopeId): self
    {
        $this->activeScope = $scopeId;

        if (! isset($this->scopes[$scopeId])) {
            $this->scopes[$scopeId] = [];
        }

        return $this;
    }

    /**
     * Register a handler in the current scope.
     *
     * @param  class-string<ExtensionPointContract>  $extensionPointClass
     * @param  string|callable  $handler
     * @return $this
     */
    public function register(
        string $extensionPointClass,
        string|callable $handler,
        int $priority = 100,
    ): self {
        if ($this->activeScope === null) {
            throw new \RuntimeException('No scope is active. Call forRequest(), forTenant(), or scope() first.');
        }

        // Register with the main registry
        $this->registry->register($extensionPointClass, $handler, $priority);

        // Track in scope for later cleanup
        $this->scopes[$this->activeScope][] = [
            'extension' => $extensionPointClass,
            'handler' => $handler,
            'priority' => $priority,
        ];

        return $this;
    }

    /**
     * Clear all handlers in a scope.
     *
     * @return $this
     */
    public function clearScope(string $scopeId): self
    {
        if (! isset($this->scopes[$scopeId])) {
            return $this;
        }

        foreach ($this->scopes[$scopeId] as $handlerDef) {
            $this->registry->removeHandler(
                $handlerDef['extension'],
                $handlerDef['handler']
            );
        }

        unset($this->scopes[$scopeId]);

        return $this;
    }

    /**
     * Clear the current request scope.
     *
     * @return $this
     */
    public function clearRequestScope(): self
    {
        $scopeId = 'request:' . request()?->fingerprint() ?? 'console';

        return $this->clearScope($scopeId);
    }

    /**
     * Clear all tenant scopes.
     *
     * @return $this
     */
    public function clearTenantScope(int|string $tenantId): self
    {
        return $this->clearScope('tenant:' . $tenantId);
    }

    /**
     * Get all scopes.
     *
     * @return array<string>
     */
    public function getScopes(): array
    {
        return array_keys($this->scopes);
    }

    /**
     * Check if a scope exists.
     */
    public function hasScope(string $scopeId): bool
    {
        return isset($this->scopes[$scopeId]);
    }

    /**
     * Get handlers in a scope.
     *
     * @return array<array{extension: class-string<ExtensionPointContract>, handler: string|callable, priority: int}>
     */
    public function getHandlersInScope(string $scopeId): array
    {
        return $this->scopes[$scopeId] ?? [];
    }
}
