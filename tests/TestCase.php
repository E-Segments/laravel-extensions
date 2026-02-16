<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Tests;

use Esegments\LaravelExtensions\ExtensionServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ExtensionServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Extensions' => \Esegments\LaravelExtensions\Facades\Extensions::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('extensions.debug', false);
    }
}
