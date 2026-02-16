<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Console;

use Esegments\LaravelExtensions\Discovery\AttributeDiscovery;
use Esegments\LaravelExtensions\HandlerRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Command to cache discovered handlers for production.
 *
 * @example
 * ```bash
 * php artisan extension:cache
 * ```
 */
class CacheCommand extends Command
{
    protected $signature = 'extension:cache';

    protected $description = 'Cache discovered extension handlers for production';

    public function handle(HandlerRegistry $registry, AttributeDiscovery $discovery): int
    {
        $this->info('Caching extension handlers...');

        $directories = $this->getDiscoveryDirectories();

        if (empty($directories)) {
            $this->warn('No discovery directories configured.');
            $this->line('Configure directories in config/extensions.php under discovery.directories');

            return self::SUCCESS;
        }

        // Clear existing cache
        $cacheKey = config('extensions.discovery.cache_key', 'extensions.discovered_handlers');
        Cache::forget($cacheKey);

        // Clear registry and rediscover
        $registry->clear();

        // Discover handlers
        $count = $discovery->discoverAndRegister($directories);

        if ($count === 0) {
            $this->warn('No handlers discovered.');

            return self::SUCCESS;
        }

        // Cache discovered handlers
        $handlers = $discovery->getDiscoveredHandlers();
        Cache::forever($cacheKey, $handlers);

        $this->info("Cached {$count} handler(s) from " . count($directories) . ' directory(ies)');

        // List cached handlers
        $this->newLine();
        $this->table(
            ['Extension Point', 'Handler', 'Priority'],
            collect($handlers)->map(fn ($h) => [
                $this->formatClassName($h['extensionPoint']),
                $this->formatClassName($h['handler']),
                $h['priority'],
            ])->toArray()
        );

        return self::SUCCESS;
    }

    /**
     * Get discovery directories as absolute paths.
     *
     * @return array<string>
     */
    private function getDiscoveryDirectories(): array
    {
        $configured = config('extensions.discovery.directories', []);
        $directories = [];

        foreach ($configured as $dir) {
            $path = base_path($dir);
            if (is_dir($path)) {
                $directories[] = $path;
            }
        }

        return $directories;
    }

    /**
     * Format a class name for display.
     */
    private function formatClassName(string $className): string
    {
        $parts = explode('\\', $className);

        return array_pop($parts);
    }
}
