<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Tests\Fixtures;

use Esegments\LaravelExtensions\Contracts\ExtensionHandlerContract;
use Esegments\LaravelExtensions\Contracts\ExtensionPointContract;

final class SimpleHandler implements ExtensionHandlerContract
{
    public function __construct(
        public readonly string $name = 'SimpleHandler',
    ) {}

    public function handle(ExtensionPointContract $extensionPoint): mixed
    {
        if ($extensionPoint instanceof SimpleExtensionPoint) {
            $extensionPoint->processed[] = $this->name;
        }

        if ($extensionPoint instanceof InterruptibleExtensionPoint) {
            $extensionPoint->processed[] = $this->name;
        }

        return null;
    }
}
