<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Middleware;

use Closure;
use Esegments\LaravelExtensions\Scoping\ScopedRegistry;
use Illuminate\Http\Request;

/**
 * Middleware to clear request-scoped handlers after the response.
 *
 * Add this middleware to your HTTP kernel to automatically clean up
 * handlers that were registered for the current request only.
 *
 * @example
 * ```php
 * // In app/Http/Kernel.php
 * protected $middleware = [
 *     // ...
 *     \Esegments\LaravelExtensions\Middleware\ClearRequestScopedHandlers::class,
 * ];
 * ```
 */
final class ClearRequestScopedHandlers
{
    public function __construct(
        private readonly ScopedRegistry $scopedRegistry,
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        return $next($request);
    }

    /**
     * Handle tasks after the response has been sent.
     */
    public function terminate(Request $request, mixed $response): void
    {
        $this->scopedRegistry->clearRequestScope();
    }
}
