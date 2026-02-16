<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Scoping;

/**
 * Request scope utilities.
 *
 * Provides helpers for working with request-scoped handlers.
 */
final class RequestScope
{
    /**
     * Generate a unique scope ID for the current request.
     */
    public static function id(): string
    {
        return 'request:' . (request()?->fingerprint() ?? uniqid('console-'));
    }

    /**
     * Check if we're in a request context.
     */
    public static function inRequestContext(): bool
    {
        return request() !== null && ! app()->runningInConsole();
    }
}
