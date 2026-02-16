<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Bridges\Filament;

use Illuminate\Support\ServiceProvider;

/**
 * Service provider for Filament bridge.
 */
class FilamentBridgeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The trait handles everything
    }

    public function boot(): void
    {
        // Log if bridge is enabled
        if (config('extensions.bridges.filament', false) && config('extensions.debug', false)) {
            logger()->debug('[Extensions] Filament bridge enabled');
        }
    }
}
