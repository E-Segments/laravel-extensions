<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Console;

use Esegments\LaravelExtensions\CircuitBreaker\CircuitBreaker;
use Esegments\LaravelExtensions\Contracts\AsyncHandlerContract;
use Esegments\LaravelExtensions\Contracts\ExtensionPointContract;
use Esegments\LaravelExtensions\Contracts\InterruptibleContract;
use Esegments\LaravelExtensions\Contracts\PipeableContract;
use Esegments\LaravelExtensions\HandlerRegistry;
use Illuminate\Console\Command;
use ReflectionClass;

/**
 * Command to inspect a specific extension point in detail.
 *
 * @example
 * ```bash
 * php artisan extension:inspect UserCreated
 * php artisan extension:inspect "App\Extensions\UserCreated"
 * ```
 */
class InspectCommand extends Command
{
    protected $signature = 'extension:inspect
        {extension : The extension point class name (full or partial)}';

    protected $description = 'Inspect a specific extension point in detail';

    public function handle(HandlerRegistry $registry, ?CircuitBreaker $circuitBreaker = null): int
    {
        $search = $this->argument('extension');
        $extensionPoints = $registry->getRegisteredExtensionPoints();

        // Find matching extension point
        $matches = array_filter($extensionPoints, function ($ep) use ($search) {
            return str_contains($ep, $search);
        });

        if (empty($matches)) {
            $this->error("No extension point found matching: {$search}");

            return self::FAILURE;
        }

        if (count($matches) > 1) {
            $extensionPointClass = $this->choice(
                'Multiple extension points found. Which one?',
                array_values($matches)
            );
        } else {
            $extensionPointClass = array_values($matches)[0];
        }

        $this->displayExtensionPointInfo($extensionPointClass, $registry, $circuitBreaker);

        return self::SUCCESS;
    }

    private function displayExtensionPointInfo(
        string $extensionPointClass,
        HandlerRegistry $registry,
        ?CircuitBreaker $circuitBreaker
    ): void {
        $this->newLine();
        $this->line("<fg=white;options=bold>Extension Point: {$extensionPointClass}</>");
        $this->newLine();

        // Show contracts implemented
        if (class_exists($extensionPointClass)) {
            $this->displayContracts($extensionPointClass);
            $this->displayProperties($extensionPointClass);
        }

        // Show handlers
        $handlers = $registry->getHandlers($extensionPointClass);

        if (empty($handlers)) {
            $this->warn('No handlers registered.');

            return;
        }

        $this->info("Handlers ({$registry->countHandlers($extensionPointClass)}):");
        $this->newLine();

        foreach ($handlers as $index => $handlerDef) {
            $this->displayHandlerInfo($index + 1, $handlerDef, $circuitBreaker);
        }
    }

    private function displayContracts(string $className): void
    {
        $contracts = [];

        if (is_a($className, ExtensionPointContract::class, true)) {
            $contracts[] = 'ExtensionPoint';
        }
        if (is_a($className, InterruptibleContract::class, true)) {
            $contracts[] = '<fg=yellow>Interruptible</>';
        }
        if (is_a($className, PipeableContract::class, true)) {
            $contracts[] = '<fg=cyan>Pipeable</>';
        }

        if (! empty($contracts)) {
            $this->line('Contracts: ' . implode(', ', $contracts));
        }
    }

    private function displayProperties(string $className): void
    {
        try {
            $reflection = new ReflectionClass($className);
            $constructor = $reflection->getConstructor();

            if ($constructor === null) {
                return;
            }

            $params = $constructor->getParameters();
            if (empty($params)) {
                return;
            }

            $this->line('Properties:');
            foreach ($params as $param) {
                $type = $param->getType();
                $typeName = $type ? $type->getName() : 'mixed';
                $optional = $param->isOptional() ? ' = ' . json_encode($param->getDefaultValue()) : '';
                $this->line("  - <fg=green>{$param->getName()}</>: <fg=gray>{$typeName}</>{$optional}");
            }
            $this->newLine();
        } catch (\Throwable) {
            // Ignore reflection errors
        }
    }

    private function displayHandlerInfo(int $index, array $handlerDef, ?CircuitBreaker $circuitBreaker): void
    {
        $handler = $handlerDef['handler'];
        $priority = $handlerDef['priority'];
        $tags = $handlerDef['tags'] ?? [];

        if (is_string($handler)) {
            $this->line("  {$index}. <fg=white>{$handler}</>");

            // Show handler type
            $types = [];
            if (is_a($handler, AsyncHandlerContract::class, true)) {
                $types[] = '<fg=magenta>async</>';
            }

            if (! empty($types)) {
                $this->line("     Type: " . implode(', ', $types));
            }

            // Show circuit breaker status
            if ($circuitBreaker && class_exists($handler)) {
                $state = $circuitBreaker->status($handler);
                $color = match ($state->value) {
                    'closed' => 'green',
                    'open' => 'red',
                    'half_open' => 'yellow',
                };
                $this->line("     Circuit: <fg={$color}>{$state->getLabel()}</>");
            }
        } else {
            $this->line("  {$index}. <fg=cyan>Closure</>");
        }

        $this->line("     Priority: <fg=gray>{$priority}</>");

        if (! empty($tags)) {
            $this->line("     Tags: <fg=gray>" . implode(', ', $tags) . "</>");
        }

        $this->newLine();
    }
}
