<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Concerns;

use Esegments\LaravelExtensions\Attributes\Deprecated;
use Illuminate\Support\Facades\Log;
use ReflectionClass;

/**
 * Trait for handling deprecated handlers.
 */
trait HandlesDeprecation
{
    /**
     * Deprecation info cache.
     *
     * @var array<string, Deprecated|null>
     */
    private array $deprecationCache = [];

    /**
     * Check if a handler is deprecated.
     *
     * @param  class-string  $handlerClass
     */
    protected function isDeprecated(string $handlerClass): bool
    {
        return $this->getDeprecation($handlerClass) !== null;
    }

    /**
     * Get deprecation info for a handler.
     *
     * @param  class-string  $handlerClass
     */
    protected function getDeprecation(string $handlerClass): ?Deprecated
    {
        if (! array_key_exists($handlerClass, $this->deprecationCache)) {
            $this->deprecationCache[$handlerClass] = $this->findDeprecation($handlerClass);
        }

        return $this->deprecationCache[$handlerClass];
    }

    /**
     * Log a deprecation warning.
     *
     * @param  class-string  $handlerClass
     */
    protected function logDeprecation(string $handlerClass): void
    {
        $deprecation = $this->getDeprecation($handlerClass);

        if ($deprecation === null) {
            return;
        }

        $message = $deprecation->getMessage($handlerClass);

        Log::warning("[Extensions] {$message}");
    }

    /**
     * Find deprecation attribute on a handler class.
     *
     * @param  class-string  $handlerClass
     */
    private function findDeprecation(string $handlerClass): ?Deprecated
    {
        if (! class_exists($handlerClass)) {
            return null;
        }

        try {
            $reflection = new ReflectionClass($handlerClass);
            $attributes = $reflection->getAttributes(Deprecated::class);

            if (empty($attributes)) {
                return null;
            }

            return $attributes[0]->newInstance();
        } catch (\Throwable) {
            return null;
        }
    }
}
