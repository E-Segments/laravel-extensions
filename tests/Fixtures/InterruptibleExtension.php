<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Tests\Fixtures;

use Esegments\LaravelExtensions\Concerns\InterruptibleTrait;
use Esegments\LaravelExtensions\Contracts\InterruptibleContract;
use Esegments\LaravelExtensions\Contracts\PipeableContract;

final class InterruptibleExtension implements InterruptibleContract, PipeableContract
{
    use InterruptibleTrait;

    public array $errors = [];

    public int $processedCount = 0;

    public function __construct(
        public readonly string $orderId = 'test-order',
        public readonly float $total = 100.00,
    ) {}

    public function addError(string $error): void
    {
        $this->errors[] = $error;
    }

    public function hasErrors(): bool
    {
        return ! empty($this->errors);
    }

    public function incrementProcessed(): void
    {
        $this->processedCount++;
    }
}
