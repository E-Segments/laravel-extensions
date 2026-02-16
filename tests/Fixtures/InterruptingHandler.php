<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Tests\Fixtures;

use Esegments\LaravelExtensions\Contracts\ExtensionHandlerContract;
use Esegments\LaravelExtensions\Contracts\ExtensionPointContract;

final class InterruptingHandler implements ExtensionHandlerContract
{
    public function __construct(
        private readonly float $maxTotal = 500.00,
    ) {}

    public function handle(ExtensionPointContract $extensionPoint): mixed
    {
        if (! $extensionPoint instanceof InterruptibleExtension) {
            return null;
        }

        $extensionPoint->incrementProcessed();

        if ($extensionPoint->total > $this->maxTotal) {
            $extensionPoint->addError("Order total exceeds maximum of {$this->maxTotal}");

            return false; // Interrupt processing
        }

        return null;
    }
}
