<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Contracts;

/**
 * Contract for extension points that can be interrupted (vetoed).
 *
 * When a handler returns `false`, the extension point is marked as interrupted
 * and no further handlers are executed. This is useful for validation scenarios
 * where you want to stop processing if a condition fails.
 *
 * @example
 * ```php
 * final class ValidateOrderExtension implements InterruptibleContract
 * {
 *     use InterruptibleTrait;
 *
 *     public function __construct(
 *         public readonly Order $order,
 *     ) {}
 * }
 *
 * // In handler:
 * public function handle(ExtensionPointContract $ext): mixed
 * {
 *     if ($ext->order->total > 10000) {
 *         return false; // Veto the order
 *     }
 *     return null;
 * }
 *
 * // In dispatcher caller:
 * $canProceed = Extensions::dispatchInterruptible($extension);
 * if (! $canProceed) {
 *     echo "Interrupted by: " . $extension->getInterruptedBy();
 * }
 * ```
 */
interface InterruptibleContract extends ExtensionPointContract
{
    /**
     * Check if this extension point was interrupted by a handler.
     */
    public function wasInterrupted(): bool;

    /**
     * Mark this extension point as interrupted.
     */
    public function interrupt(): void;

    /**
     * Get the class name of the handler that interrupted this extension point.
     */
    public function getInterruptedBy(): ?string;

    /**
     * Set the class name of the handler that interrupted this extension point.
     */
    public function setInterruptedBy(string $handlerClass): void;
}
