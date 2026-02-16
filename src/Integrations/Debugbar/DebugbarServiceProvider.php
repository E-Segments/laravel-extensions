<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Integrations\Debugbar;

use Illuminate\Support\ServiceProvider;

/**
 * Service provider for Laravel Debugbar integration.
 *
 * Automatically registers the extension collector when Debugbar is available.
 */
class DebugbarServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Only register if Debugbar is available
        if (! $this->app->bound('debugbar')) {
            return;
        }

        // Don't register if integrations are disabled
        if (! config('extensions.integrations.debugbar', true)) {
            return;
        }

        $this->app->singleton(ExtensionCollector::class, function (): ExtensionCollector {
            return new ExtensionCollector;
        });
    }

    public function boot(): void
    {
        // Only boot if Debugbar is available and enabled
        if (! $this->app->bound('debugbar')) {
            return;
        }

        if (! config('extensions.integrations.debugbar', true)) {
            return;
        }

        $debugbar = $this->app->make('debugbar');

        if (method_exists($debugbar, 'addCollector')) {
            $debugbar->addCollector($this->app->make(ExtensionCollector::class));
        }
    }
}
