<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Tests\Fixtures;

use Esegments\LaravelExtensions\Contracts\PipeableContract;

final class PipeableExtensionPoint implements PipeableContract
{
    public function __construct(
        public float $price = 100.0,
        public array $transformations = [],
    ) {}
}
