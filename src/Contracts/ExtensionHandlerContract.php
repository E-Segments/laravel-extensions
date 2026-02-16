<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Contracts;

/**
 * Contract for extension point handlers.
 *
 * Handlers respond to extension points and can:
 * - Modify data (for PipeableContract extension points)
 * - Return false to interrupt processing (for InterruptibleContract extension points)
 * - Perform side effects (logging, notifications, etc.)
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
 *                 $extensionPoint->addError("Insufficient stock for {$item->product_id}");
 *                 return false; // Interrupt processing
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
     * @return mixed Return false to interrupt (only for InterruptibleContract), null/void otherwise
     */
    public function handle(ExtensionPointContract $extensionPoint): mixed;
}
