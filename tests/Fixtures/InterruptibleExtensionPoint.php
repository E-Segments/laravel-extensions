<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Tests\Fixtures;

use Esegments\LaravelExtensions\Concerns\InterruptibleTrait;
use Esegments\LaravelExtensions\Contracts\InterruptibleContract;

final class InterruptibleExtensionPoint implements InterruptibleContract
{
    use InterruptibleTrait;

    public array $errors = [];

    public array $processed = [];

    public function __construct(
        public readonly string $data = 'test',
    ) {}

    public function addError(string $error): void
    {
        $this->errors[] = $error;
    }

    public function hasErrors(): bool
    {
        return ! empty($this->errors);
    }
}
