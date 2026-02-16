<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Contracts;

use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Contract for handlers that should run asynchronously.
 *
 * Handlers implementing this contract (and ShouldQueue) will be dispatched
 * to a queue instead of running synchronously.
 *
 * @example
 * ```php
 * final class SendNotificationHandler implements ExtensionHandlerContract, AsyncHandlerContract, ShouldQueue
 * {
 *     use Queueable;
 *
 *     public string $queue = 'notifications';
 *     public int $tries = 3;
 *
 *     public function handle(ExtensionPointContract $extensionPoint): mixed
 *     {
 *         // This runs asynchronously in the queue
 *     }
 * }
 * ```
 */
interface AsyncHandlerContract extends ShouldQueue
{
    // Marker interface - actual queue configuration comes from ShouldQueue
}
