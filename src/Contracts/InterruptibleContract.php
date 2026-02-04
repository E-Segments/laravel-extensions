<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Contracts;

/**
 * Extension points that can be vetoed/interrupted by handlers.
 *
 * When a handler returns `false` for an interruptible extension point,
 * no further handlers will be executed and the operation can be cancelled.
 *
 * Use this for validation, authorization, or any scenario where handlers
 * should be able to prevent an operation from proceeding.
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
 * // In a handler:
 * public function handle(ExtensionPointContract $ext): mixed
 * {
 *     if ($ext->order->total > 10000) {
 *         return false; // Veto the operation
 *     }
 *     return null;
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
     * Set the handler class that caused the interruption.
     */
    public function setInterruptedBy(string $handlerClass): void;
}
