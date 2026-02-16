<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Scoping;

/**
 * Tenant scope utilities.
 *
 * Provides helpers for working with tenant-scoped handlers.
 */
final class TenantScope
{
    /**
     * Current tenant ID (can be set externally).
     */
    private static int|string|null $currentTenantId = null;

    /**
     * Set the current tenant ID.
     */
    public static function setCurrentTenant(int|string|null $tenantId): void
    {
        self::$currentTenantId = $tenantId;
    }

    /**
     * Get the current tenant ID.
     */
    public static function getCurrentTenant(): int|string|null
    {
        return self::$currentTenantId;
    }

    /**
     * Generate a scope ID for a tenant.
     */
    public static function id(int|string $tenantId): string
    {
        return 'tenant:' . $tenantId;
    }

    /**
     * Check if a tenant is currently active.
     */
    public static function hasTenant(): bool
    {
        return self::$currentTenantId !== null;
    }

    /**
     * Clear the current tenant.
     */
    public static function clear(): void
    {
        self::$currentTenantId = null;
    }
}
