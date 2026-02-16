<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Bridges\Eloquent;

use Illuminate\Support\ServiceProvider;

/**
 * Service provider for Eloquent bridge.
 */
class EloquentBridgeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The trait handles everything
    }

    public function boot(): void
    {
        // Log if bridge is enabled
        if (config('extensions.bridges.eloquent', false) && config('extensions.debug', false)) {
            logger()->debug('[Extensions] Eloquent bridge enabled');
        }
    }
}
