<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Console;

use Esegments\LaravelExtensions\Contracts\ExtensionPointContract;
use Esegments\LaravelExtensions\ExtensionDispatcher;
use Esegments\LaravelExtensions\HandlerRegistry;
use Illuminate\Console\Command;
use ReflectionClass;
use Throwable;

/**
 * Command to test extension point handler execution.
 *
 * @example
 * ```bash
 * php artisan extension:test UserCreated
 * php artisan extension:test UserCreated --with-data='{"user_id": 1}'
 * php artisan extension:test UserCreated --dry-run
 * ```
 */
class TestCommand extends Command
{
    protected $signature = 'extension:test
        {extension : The extension point class name (full or partial)}
        {--with-data= : JSON data to pass to the extension point constructor}
        {--dry-run : Show what would happen without actually executing}';

    protected $description = 'Test extension point handler execution';

    public function handle(
        HandlerRegistry $registry,
        ExtensionDispatcher $dispatcher
    ): int {
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

        return $this->testExtensionPoint($extensionPointClass, $registry, $dispatcher);
    }

    private function testExtensionPoint(
        string $extensionPointClass,
        HandlerRegistry $registry,
        ExtensionDispatcher $dispatcher
    ): int {
        $this->newLine();
        $this->info("Testing: {$extensionPointClass}");
        $this->newLine();

        $handlers = $registry->getHandlers($extensionPointClass);

        if (empty($handlers)) {
            $this->warn('No handlers registered for this extension point.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Found %d handler(s)', count($handlers)));

        // Show handlers that will be executed
        foreach ($handlers as $index => $handlerDef) {
            $handler = $handlerDef['handler'];
            $name = is_string($handler) ? $handler : 'Closure';
            $this->line(sprintf('  %d. %s (priority: %d)', $index + 1, $name, $handlerDef['priority']));
        }

        $this->newLine();

        if ($this->option('dry-run')) {
            $this->info('Dry run complete. Use --no-dry-run to actually execute handlers.');

            return self::SUCCESS;
        }

        // Try to create the extension point instance
        try {
            $extensionPoint = $this->createExtensionPoint($extensionPointClass);
        } catch (Throwable $e) {
            $this->error("Could not create extension point instance: {$e->getMessage()}");
            $this->newLine();
            $this->warn('Provide constructor data with --with-data=\'{"key": "value"}\'');

            return self::FAILURE;
        }

        // Execute
        $this->info('Executing handlers...');
        $this->newLine();

        try {
            $result = $dispatcher->gracefully()->dispatchWithResults($extensionPoint);

            // Show results
            foreach ($result->successful() as $handlerClass) {
                $this->line("<fg=green>  ✓</> {$handlerClass}");
            }

            foreach ($result->errors() as $handlerClass => $error) {
                $this->line("<fg=red>  ✗</> {$handlerClass}");
                $this->line("    <fg=gray>{$error->getMessage()}</>");
            }

            foreach ($result->skipped() as $handlerClass => $reason) {
                $this->line("<fg=yellow>  ○</> {$handlerClass} (skipped: {$reason})");
            }

            $this->newLine();

            if ($result->hasErrors()) {
                $this->warn(sprintf(
                    'Completed with %d error(s)',
                    $result->errors()->count()
                ));

                return self::FAILURE;
            }

            $totalTime = $result->totalTime();
            if ($totalTime !== null) {
                $this->info(sprintf('Completed in %.2fms', $totalTime));
            } else {
                $this->info('Completed successfully');
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Execution failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    /**
     * Create an extension point instance from JSON data or defaults.
     */
    private function createExtensionPoint(string $extensionPointClass): ExtensionPointContract
    {
        $data = $this->option('with-data');

        if ($data !== null) {
            $decoded = json_decode($data, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \InvalidArgumentException('Invalid JSON data: ' . json_last_error_msg());
            }

            return new $extensionPointClass(...$decoded);
        }

        // Try to create with no arguments
        $reflection = new ReflectionClass($extensionPointClass);
        $constructor = $reflection->getConstructor();

        if ($constructor === null || $constructor->getNumberOfRequiredParameters() === 0) {
            return new $extensionPointClass;
        }

        throw new \InvalidArgumentException(
            'Extension point requires constructor arguments. Use --with-data to provide them.'
        );
    }
}
