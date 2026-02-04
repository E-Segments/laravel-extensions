<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Tests\Fixtures;

use Esegments\LaravelExtensions\Contracts\ExtensionHandlerContract;
use Esegments\LaravelExtensions\Contracts\ExtensionPointContract;

final class VetoHandler implements ExtensionHandlerContract
{
    public function __construct(
        public readonly bool $shouldVeto = true,
    ) {}

    public function handle(ExtensionPointContract $extensionPoint): mixed
    {
        if ($extensionPoint instanceof InterruptibleExtensionPoint) {
            $extensionPoint->processed[] = 'VetoHandler';

            if ($this->shouldVeto) {
                $extensionPoint->addError('Vetoed by VetoHandler');

                return false;
            }
        }

        return null;
    }
}
