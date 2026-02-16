<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Command to clear cached extension handlers.
 *
 * @example
 * ```bash
 * php artisan extension:clear
 * ```
 */
class ClearCommand extends Command
{
    protected $signature = 'extension:clear';

    protected $description = 'Clear cached extension handlers';

    public function handle(): int
    {
        $cacheKey = config('extensions.discovery.cache_key', 'extensions.discovered_handlers');

        Cache::forget($cacheKey);

        $this->info('Extension handler cache cleared.');

        return self::SUCCESS;
    }
}
