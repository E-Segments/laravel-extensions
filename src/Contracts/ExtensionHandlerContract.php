<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Contracts;

/**
 * Contract for extension point handlers.
 *
 * Handlers process extension points when they are dispatched. Each handler
 * receives the extension point object and can:
 *
 * - Read data from the extension point
 * - Modify data if the extension point implements PipeableContract
 * - Return `false` to interrupt if the extension point implements InterruptibleContract
 * - Perform side effects (logging, notifications, etc.)
 *
 * Handlers are resolved from the container, so you can inject dependencies.
 *
 * @example
 * ```php
 * final class CheckInventoryHandler implements ExtensionHandlerContract
 * {
 *     public function __construct(
 *         private readonly InventoryService $inventory,
 *     ) {}
 *
 *     public function handle(ExtensionPointContract $extensionPoint): mixed
 *     {
 *         if (! $extensionPoint instanceof ValidateOrderExtension) {
 *             return null;
 *         }
 *
 *         foreach ($extensionPoint->order->items as $item) {
 *             if (! $this->inventory->hasStock($item->product_id, $item->quantity)) {
 *                 return false; // Veto the order
 *             }
 *         }
 *
 *         return null;
 *     }
 * }
 * ```
 */
interface ExtensionHandlerContract
{
    /**
     * Handle the extension point.
     *
     * @param  ExtensionPointContract  $extensionPoint  The extension point to handle
     * @return bool|void|null Return `false` to interrupt (only for InterruptibleContract)
     */
    public function handle(ExtensionPointContract $extensionPoint): mixed;
}
