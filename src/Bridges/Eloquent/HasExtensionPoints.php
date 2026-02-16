<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Bridges\Eloquent;

use Esegments\LaravelExtensions\ExtensionDispatcher;

/**
 * Trait to add extension point dispatching to Eloquent models.
 *
 * @example
 * ```php
 * class User extends Model
 * {
 *     use HasExtensionPoints;
 *
 *     // Optional: customize which events dispatch extension points
 *     protected array $extensionPoints = [
 *         'created' => UserCreated::class,  // Use custom class
 *         'updated' => true,                 // Use auto-generated
 *         'deleted' => false,                // Disable
 *     ];
 * }
 * ```
 */
trait HasExtensionPoints
{
    /**
     * Boot the trait.
     */
    public static function bootHasExtensionPoints(): void
    {
        // Don't dispatch if bridge is disabled
        if (! config('extensions.bridges.eloquent', false)) {
            return;
        }

        $events = ['creating', 'created', 'updating', 'updated', 'deleting', 'deleted', 'restoring', 'restored'];

        foreach ($events as $event) {
            static::$event(function ($model) use ($event) {
                $model->dispatchModelExtensionPoint($event);
            });
        }
    }

    /**
     * Dispatch an extension point for a model event.
     */
    protected function dispatchModelExtensionPoint(string $event): void
    {
        $config = $this->extensionPoints ?? [];

        // Check if this event is disabled
        if (isset($config[$event]) && $config[$event] === false) {
            return;
        }

        // Get extension point class
        $extensionPointClass = $this->getExtensionPointClass($event, $config);

        if ($extensionPointClass === null) {
            return;
        }

        // Create extension point instance
        $extensionPoint = $this->createExtensionPoint($extensionPointClass, $event);

        // Dispatch
        app(ExtensionDispatcher::class)->dispatch($extensionPoint);
    }

    /**
     * Get the extension point class for an event.
     *
     * @return class-string|null
     */
    protected function getExtensionPointClass(string $event, array $config): ?string
    {
        // Check for custom class in config
        if (isset($config[$event]) && is_string($config[$event])) {
            return $config[$event];
        }

        // Generate class name: {Model}{Event} (e.g., UserCreated)
        $modelName = class_basename(static::class);
        $eventName = ucfirst($event);
        $className = $modelName . $eventName;

        // Try common namespaces
        $namespaces = [
            'App\\Extensions\\',
            'App\\Extensions\\Models\\',
            'App\\Events\\',
        ];

        foreach ($namespaces as $namespace) {
            $fullClass = $namespace . $className;
            if (class_exists($fullClass)) {
                return $fullClass;
            }
        }

        // Fall back to generic ModelExtensionPoint
        return ModelExtensionPoint::class;
    }

    /**
     * Create an extension point instance.
     */
    protected function createExtensionPoint(string $extensionPointClass, string $event): object
    {
        // For generic ModelExtensionPoint
        if ($extensionPointClass === ModelExtensionPoint::class) {
            return new ModelExtensionPoint($this, $event);
        }

        // For custom classes, try to instantiate with model
        return new $extensionPointClass($this);
    }
}
