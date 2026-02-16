<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Console;

use Esegments\LaravelExtensions\CircuitBreaker\CircuitBreaker;
use Esegments\LaravelExtensions\CircuitBreaker\CircuitState;
use Esegments\LaravelExtensions\HandlerRegistry;
use Illuminate\Console\Command;

/**
 * Command to show extension execution statistics.
 *
 * @example
 * ```bash
 * php artisan extension:stats
 * php artisan extension:stats --point=UserCreated
 * ```
 */
class StatsCommand extends Command
{
    protected $signature = 'extension:stats
        {--point= : Filter by extension point}';

    protected $description = 'Show extension execution statistics';

    public function handle(HandlerRegistry $registry, ?CircuitBreaker $circuitBreaker = null): int
    {
        $extensionPoints = $registry->getRegisteredExtensionPoints();
        $pointFilter = $this->option('point');

        if (empty($extensionPoints)) {
            $this->info('No extension points registered.');

            return self::SUCCESS;
        }

        $this->displaySummary($extensionPoints, $registry);
        $this->newLine();

        if ($circuitBreaker && $circuitBreaker->isEnabled()) {
            $this->displayCircuitBreakerStatus($extensionPoints, $registry, $circuitBreaker, $pointFilter);
        }

        $this->displayGroupStats($registry);
        $this->displayTagStats($registry);

        return self::SUCCESS;
    }

    private function displaySummary(array $extensionPoints, HandlerRegistry $registry): void
    {
        $totalHandlers = array_sum(
            array_map(fn ($ep) => $registry->countHandlers($ep), $extensionPoints)
        );

        $unusedPoints = array_filter(
            $extensionPoints,
            fn ($ep) => $registry->countHandlers($ep) === 0
        );

        $this->info('Summary');
        $this->line('  Extension Points: ' . count($extensionPoints));
        $this->line('  Total Handlers: ' . $totalHandlers);
        $this->line('  Groups: ' . count($registry->getRegisteredGroups()));
        $this->line('  Tags: ' . count($registry->getRegisteredTags()));

        if (! empty($unusedPoints)) {
            $this->line('  <fg=yellow>Unused Points: ' . count($unusedPoints) . '</>');
        }
    }

    private function displayCircuitBreakerStatus(
        array $extensionPoints,
        HandlerRegistry $registry,
        CircuitBreaker $circuitBreaker,
        ?string $filter
    ): void {
        $this->info('Circuit Breaker Status');

        $hasOpenCircuits = false;
        $rows = [];

        foreach ($extensionPoints as $extensionPointClass) {
            if ($filter && ! str_contains($extensionPointClass, $filter)) {
                continue;
            }

            $handlers = $registry->getHandlers($extensionPointClass);

            foreach ($handlers as $handlerDef) {
                if (! is_string($handlerDef['handler'])) {
                    continue;
                }

                $state = $circuitBreaker->status($handlerDef['handler']);
                $failureCount = $circuitBreaker->failureCount($handlerDef['handler']);

                if ($state !== CircuitState::Closed || $failureCount > 0) {
                    $hasOpenCircuits = true;

                    $stateDisplay = match ($state) {
                        CircuitState::Closed => '<fg=green>Closed</>',
                        CircuitState::Open => '<fg=red>Open</>',
                        CircuitState::HalfOpen => '<fg=yellow>Half-Open</>',
                    };

                    $rows[] = [
                        $this->formatClassName($handlerDef['handler']),
                        $stateDisplay,
                        $failureCount,
                    ];
                }
            }
        }

        if (! $hasOpenCircuits) {
            $this->line('  <fg=green>All circuits closed (healthy)</>');
        } else {
            $this->newLine();
            $this->table(
                ['Handler', 'State', 'Failures'],
                $rows
            );
        }

        $this->newLine();
    }

    private function displayGroupStats(HandlerRegistry $registry): void
    {
        $groups = $registry->getRegisteredGroups();

        if (empty($groups)) {
            return;
        }

        $this->info('Handler Groups');

        foreach ($groups as $group) {
            $status = $registry->isGroupDisabled($group)
                ? '<fg=red>disabled</>'
                : '<fg=green>enabled</>';
            $this->line("  - {$group}: {$status}");
        }

        $this->newLine();
    }

    private function displayTagStats(HandlerRegistry $registry): void
    {
        $tags = $registry->getRegisteredTags();

        if (empty($tags)) {
            return;
        }

        $this->info('Handler Tags');

        foreach ($tags as $tag) {
            $count = $registry->tagged($tag)->count();
            $status = $registry->isTagDisabled($tag)
                ? '<fg=red>disabled</>'
                : '<fg=green>enabled</>';
            $this->line("  - {$tag}: {$count} handler(s) ({$status})");
        }

        $this->newLine();
    }

    private function formatClassName(string $className): string
    {
        $parts = explode('\\', $className);

        return array_pop($parts);
    }
}
