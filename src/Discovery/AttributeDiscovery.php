<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Discovery;

use Esegments\LaravelExtensions\Attributes\ExtensionHandler;
use Esegments\LaravelExtensions\HandlerRegistry;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use Throwable;

/**
 * Discovers and registers handlers using PHP attributes.
 *
 * Scans configured directories for classes with the #[ExtensionHandler] attribute
 * and automatically registers them with the HandlerRegistry.
 */
final class AttributeDiscovery
{
    /**
     * @var array<string, array{extensionPoint: string, handler: string, priority: int, async: bool, queue: ?string}>
     */
    private array $discoveredHandlers = [];

    public function __construct(
        private readonly HandlerRegistry $registry,
    ) {}

    /**
     * Discover and register handlers from the given directories.
     *
     * @param  array<string>  $directories  Directories to scan
     * @param  string  $baseNamespace  Base namespace for the directories
     * @return array<string, array{extensionPoint: string, handler: string, priority: int, async: bool, queue: ?string}>
     */
    public function discover(array $directories, string $baseNamespace = ''): array
    {
        $this->discoveredHandlers = [];

        foreach ($directories as $directory) {
            if (! is_dir($directory)) {
                continue;
            }

            $this->scanDirectory($directory, $baseNamespace);
        }

        return $this->discoveredHandlers;
    }

    /**
     * Discover handlers and register them with the registry.
     *
     * @param  array<string>  $directories  Directories to scan
     * @param  string  $baseNamespace  Base namespace for the directories
     */
    public function discoverAndRegister(array $directories, string $baseNamespace = ''): int
    {
        $handlers = $this->discover($directories, $baseNamespace);
        $count = 0;

        foreach ($handlers as $handler) {
            // Skip async handlers here - they'll be handled by the dispatcher
            $this->registry->register(
                $handler['extensionPoint'],
                $handler['handler'],
                $handler['priority'],
            );
            $count++;
        }

        return $count;
    }

    /**
     * Get async handler metadata for a handler class.
     *
     * @return array{async: bool, queue: ?string}|null
     */
    public function getAsyncMetadata(string $handlerClass): ?array
    {
        foreach ($this->discoveredHandlers as $handler) {
            if ($handler['handler'] === $handlerClass && $handler['async']) {
                return [
                    'async' => true,
                    'queue' => $handler['queue'],
                ];
            }
        }

        return null;
    }

    /**
     * Scan a directory for handler classes.
     */
    private function scanDirectory(string $directory, string $baseNamespace): void
    {
        $files = File::allFiles($directory);

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $className = $this->getClassNameFromFile($file->getPathname(), $directory, $baseNamespace);

            if ($className === null) {
                continue;
            }

            $this->processClass($className);
        }
    }

    /**
     * Get the fully qualified class name from a file.
     */
    private function getClassNameFromFile(string $filePath, string $baseDirectory, string $baseNamespace): ?string
    {
        // Read file to extract namespace and class name
        $contents = file_get_contents($filePath);

        if ($contents === false) {
            return null;
        }

        // Extract namespace
        $namespace = '';
        if (preg_match('/namespace\s+([^;]+);/', $contents, $matches)) {
            $namespace = $matches[1];
        }

        // Extract class name
        if (! preg_match('/class\s+(\w+)/', $contents, $matches)) {
            return null;
        }

        $className = $matches[1];

        return $namespace ? $namespace.'\\'.$className : $className;
    }

    /**
     * Process a class and extract handler attributes.
     */
    private function processClass(string $className): void
    {
        try {
            if (! class_exists($className)) {
                return;
            }

            $reflection = new ReflectionClass($className);

            // Skip abstract classes and interfaces
            if ($reflection->isAbstract() || $reflection->isInterface()) {
                return;
            }

            $attributes = $reflection->getAttributes(ExtensionHandler::class);

            foreach ($attributes as $attribute) {
                $instance = $attribute->newInstance();

                $this->discoveredHandlers[$className.':'.$instance->extensionPoint] = [
                    'extensionPoint' => $instance->extensionPoint,
                    'handler' => $className,
                    'priority' => $instance->priority,
                    'async' => $instance->async,
                    'queue' => $instance->queue,
                ];
            }
        } catch (Throwable) {
            // Skip classes that can't be reflected
        }
    }

    /**
     * Get all discovered handlers.
     *
     * @return array<string, array{extensionPoint: string, handler: string, priority: int, async: bool, queue: ?string}>
     */
    public function getDiscoveredHandlers(): array
    {
        return $this->discoveredHandlers;
    }
}
