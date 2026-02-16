<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Bridges\Livewire;

use Illuminate\Support\ServiceProvider;

/**
 * Service provider for Livewire bridge.
 */
class LivewireBridgeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The trait handles everything
    }

    public function boot(): void
    {
        // Log if bridge is enabled
        if (config('extensions.bridges.livewire', false) && config('extensions.debug', false)) {
            logger()->debug('[Extensions] Livewire bridge enabled');
        }
    }
}
