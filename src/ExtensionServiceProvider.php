<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions;

use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use Illuminate\Support\ServiceProvider;

final class ExtensionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/extensions.php', 'extensions');

        // Register HandlerRegistry as singleton (Octane-safe)
        $this->app->singleton(HandlerRegistry::class);

        // Register ExtensionDispatcher
        $this->app->singleton(ExtensionDispatcher::class, function ($app) {
            return new ExtensionDispatcher(
                container: $app,
                registry: $app->make(HandlerRegistry::class),
                events: $app->bound(EventDispatcher::class) ? $app->make(EventDispatcher::class) : null,
                dispatchAsEvents: config('extensions.dispatch_as_events', true),
            );
        });

        // Alias for convenience
        $this->app->alias(ExtensionDispatcher::class, 'extensions');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/extensions.php' => config_path('extensions.php'),
            ], 'extensions-config');
        }

        // Clear registry on Octane request termination if configured
        if (config('extensions.clear_on_octane_terminate', false)) {
            $this->app['events']->listen('Laravel\Octane\Events\RequestTerminated', function () {
                $this->app->make(HandlerRegistry::class)->clear();
            });
        }
    }

    public function provides(): array
    {
        return [
            HandlerRegistry::class,
            ExtensionDispatcher::class,
            'extensions',
        ];
    }
}
