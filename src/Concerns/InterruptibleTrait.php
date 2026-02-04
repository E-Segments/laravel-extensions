<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Concerns;

/**
 * Reusable implementation of InterruptibleContract.
 *
 * Add this trait to extension points that need veto capability.
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
 * ```
 */
trait InterruptibleTrait
{
    private bool $interrupted = false;

    private ?string $interruptedBy = null;

    /**
     * Check if this extension point was interrupted by a handler.
     */
    public function wasInterrupted(): bool
    {
        return $this->interrupted;
    }

    /**
     * Mark this extension point as interrupted.
     */
    public function interrupt(): void
    {
        $this->interrupted = true;
    }

    /**
     * Get the class name of the handler that interrupted this extension point.
     */
    public function getInterruptedBy(): ?string
    {
        return $this->interruptedBy;
    }

    /**
     * Set the handler class that caused the interruption.
     */
    public function setInterruptedBy(string $handlerClass): void
    {
        $this->interruptedBy = $handlerClass;
    }
}
