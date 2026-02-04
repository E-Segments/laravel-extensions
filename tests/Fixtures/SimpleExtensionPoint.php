<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Tests\Fixtures;

use Esegments\LaravelExtensions\Contracts\ExtensionPointContract;

final class SimpleExtensionPoint implements ExtensionPointContract
{
    public array $processed = [];

    public function __construct(
        public readonly string $data = 'test',
    ) {}
}
