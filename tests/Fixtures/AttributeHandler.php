<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Tests\Fixtures;

use Esegments\LaravelExtensions\Attributes\ExtensionHandler;
use Esegments\LaravelExtensions\Contracts\ExtensionHandlerContract;
use Esegments\LaravelExtensions\Contracts\ExtensionPointContract;

#[ExtensionHandler(SimpleExtension::class, priority: 50)]
final class AttributeHandler implements ExtensionHandlerContract
{
    public function handle(ExtensionPointContract $extensionPoint): mixed
    {
        if ($extensionPoint instanceof SimpleExtension) {
            $extensionPoint->addData('attribute_handler', 'executed');
        }

        return null;
    }
}
