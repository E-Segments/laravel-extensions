<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Concerns;

/**
 * Provides a reusable implementation of InterruptibleContract.
 *
 * Use this trait in extension point classes that implement InterruptibleContract
 * to get the standard interruption behavior.
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

    public function wasInterrupted(): bool
    {
        return $this->interrupted;
    }

    public function interrupt(): void
    {
        $this->interrupted = true;
    }

    public function getInterruptedBy(): ?string
    {
        return $this->interruptedBy;
    }

    public function setInterruptedBy(string $handlerClass): void
    {
        $this->interruptedBy = $handlerClass;
    }
}
