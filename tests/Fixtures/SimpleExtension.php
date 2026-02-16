<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Tests\Fixtures;

use Esegments\LaravelExtensions\Contracts\ExtensionPointContract;

final class SimpleExtension implements ExtensionPointContract
{
    public array $data = [];

    public function __construct(
        public readonly string $name = 'test',
    ) {}

    public function addData(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }
}
