<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Tests\Fixtures;

use Esegments\LaravelExtensions\Contracts\ExtensionHandlerContract;
use Esegments\LaravelExtensions\Contracts\ExtensionPointContract;

final class SimpleHandler implements ExtensionHandlerContract
{
    public function handle(ExtensionPointContract $extensionPoint): mixed
    {
        if ($extensionPoint instanceof SimpleExtension) {
            $extensionPoint->addData('simple_handler', 'executed');
        }

        return null;
    }
}
