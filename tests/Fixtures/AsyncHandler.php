<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Tests\Fixtures;

use Esegments\LaravelExtensions\Contracts\AsyncHandlerContract;
use Esegments\LaravelExtensions\Contracts\ExtensionHandlerContract;
use Esegments\LaravelExtensions\Contracts\ExtensionPointContract;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;

final class AsyncHandler implements AsyncHandlerContract, ExtensionHandlerContract
{
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public function __construct()
    {
        $this->onQueue('extensions');
    }

    public function handle(ExtensionPointContract $extensionPoint): mixed
    {
        if ($extensionPoint instanceof SimpleExtension) {
            $extensionPoint->addData('async_handler', 'executed');
        }

        return null;
    }
}
