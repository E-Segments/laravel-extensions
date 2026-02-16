<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Tests\Fixtures;

use Esegments\LaravelExtensions\Contracts\ExtensionHandlerContract;
use Esegments\LaravelExtensions\Contracts\ExtensionPointContract;

final class CountingHandler implements ExtensionHandlerContract
{
    public function handle(ExtensionPointContract $extensionPoint): mixed
    {
        if ($extensionPoint instanceof InterruptibleExtension) {
            $extensionPoint->incrementProcessed();
        }

        return null;
    }
}
