<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Console;

use Esegments\LaravelExtensions\HandlerRegistry;
use Illuminate\Console\Command;

/**
 * Command to list all registered extension points and handlers.
 *
 * @example
 * ```bash
 * php artisan extension:list
 * php artisan extension:list --point=UserCreated
 * php artisan extension:list --handler=AuditHandler
 * php artisan extension:list --unused
 * ```
 */
class ListCommand extends Command
{
    protected $signature = 'extension:list
        {--point= : Filter by extension point class name}
        {--handler= : Filter by handler class name}
        {--unused : Show only extension points with no handlers}
        {--tag= : Filter by handler tag}
        {--group= : Filter by handler group}';

    protected $description = 'List all registered extension points and handlers';

    public function handle(HandlerRegistry $registry): int
    {
        $extensionPoints = $registry->getRegisteredExtensionPoints();

        if (empty($extensionPoints)) {
            $this->info('No extension points registered.');

            return self::SUCCESS;
        }

        $pointFilter = $this->option('point');
        $handlerFilter = $this->option('handler');
        $showUnused = $this->option('unused');
        $tagFilter = $this->option('tag');

        $rows = [];

        foreach ($extensionPoints as $extensionPointClass) {
            // Apply point filter
            if ($pointFilter && ! str_contains($extensionPointClass, $pointFilter)) {
                continue;
            }

            $handlers = $registry->getHandlers($extensionPointClass);

            // Apply unused filter
            if ($showUnused && ! empty($handlers)) {
                continue;
            }

            if (empty($handlers)) {
                $rows[] = [
                    $this->formatClassName($extensionPointClass),
                    '<fg=gray>No handlers</>',
                    '-',
                    '-',
                ];

                continue;
            }

            foreach ($handlers as $index => $handlerDef) {
                $handlerName = is_string($handlerDef['handler'])
                    ? $this->formatClassName($handlerDef['handler'])
                    : '<fg=cyan>Closure</>';

                // Apply handler filter
                if ($handlerFilter && is_string($handlerDef['handler'])) {
                    if (! str_contains($handlerDef['handler'], $handlerFilter)) {
                        continue;
                    }
                }

                // Apply tag filter
                if ($tagFilter) {
                    $tags = $handlerDef['tags'] ?? [];
                    if (! in_array($tagFilter, $tags, true)) {
                        continue;
                    }
                }

                $tags = implode(', ', $handlerDef['tags'] ?? []) ?: '-';

                $rows[] = [
                    $index === 0 ? $this->formatClassName($extensionPointClass) : '',
                    $handlerName,
                    (string) $handlerDef['priority'],
                    $tags,
                ];
            }
        }

        if (empty($rows)) {
            $this->info('No matching extension points or handlers found.');

            return self::SUCCESS;
        }

        $this->table(
            ['Extension Point', 'Handler', 'Priority', 'Tags'],
            $rows
        );

        // Summary
        $this->newLine();
        $this->info(sprintf(
            'Total: %d extension points, %d handlers',
            count($extensionPoints),
            array_sum(array_map(fn ($ep) => $registry->countHandlers($ep), $extensionPoints))
        ));

        // Show groups
        $groups = $registry->getRegisteredGroups();
        if (! empty($groups)) {
            $this->newLine();
            $this->info('Registered groups: ' . implode(', ', $groups));
        }

        // Show tags
        $registeredTags = $registry->getRegisteredTags();
        if (! empty($registeredTags)) {
            $this->info('Registered tags: ' . implode(', ', $registeredTags));
        }

        return self::SUCCESS;
    }

    /**
     * Format a class name for display.
     */
    private function formatClassName(string $className): string
    {
        $parts = explode('\\', $className);
        $shortName = array_pop($parts);
        $namespace = implode('\\', $parts);

        if (empty($namespace)) {
            return $shortName;
        }

        return "<fg=gray>{$namespace}\\</><fg=white>{$shortName}</>";
    }
}
