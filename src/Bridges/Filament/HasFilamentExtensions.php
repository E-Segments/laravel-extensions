<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Bridges\Filament;

use Esegments\LaravelExtensions\Contracts\ExtensionPointContract;
use Esegments\LaravelExtensions\ExtensionDispatcher;

/**
 * Trait to add extension point dispatching to Filament resources.
 *
 * @example
 * ```php
 * class UserResource extends Resource
 * {
 *     use HasFilamentExtensions;
 *
 *     // Dispatches: UserResourceCreating, UserResourceCreated, etc.
 * }
 * ```
 */
trait HasFilamentExtensions
{
    /**
     * Dispatch an extension point before a resource action.
     */
    protected static function dispatchBeforeAction(string $action, ?object $record = null, array $data = []): void
    {
        if (! config('extensions.bridges.filament', false)) {
            return;
        }

        $extensionPointClass = static::getFilamentExtensionPointClass($action, 'before');

        if ($extensionPointClass === null || ! class_exists($extensionPointClass)) {
            // Fall back to generic extension point
            $extensionPoint = new FilamentActionExtensionPoint(
                resourceClass: static::class,
                action: "before_{$action}",
                record: $record,
                data: $data,
            );
        } else {
            $extensionPoint = static::createFilamentExtensionPoint($extensionPointClass, $record, $data);
        }

        app(ExtensionDispatcher::class)->dispatch($extensionPoint);
    }

    /**
     * Dispatch an extension point after a resource action.
     */
    protected static function dispatchAfterAction(string $action, ?object $record = null, array $data = []): void
    {
        if (! config('extensions.bridges.filament', false)) {
            return;
        }

        $extensionPointClass = static::getFilamentExtensionPointClass($action, 'after');

        if ($extensionPointClass === null || ! class_exists($extensionPointClass)) {
            // Fall back to generic extension point
            $extensionPoint = new FilamentActionExtensionPoint(
                resourceClass: static::class,
                action: "after_{$action}",
                record: $record,
                data: $data,
            );
        } else {
            $extensionPoint = static::createFilamentExtensionPoint($extensionPointClass, $record, $data);
        }

        app(ExtensionDispatcher::class)->dispatch($extensionPoint);
    }

    /**
     * Get the extension point class for an action.
     *
     * @return class-string|null
     */
    protected static function getFilamentExtensionPointClass(string $action, string $timing): ?string
    {
        $resourceName = class_basename(static::class);

        // Remove 'Resource' suffix if present
        if (str_ends_with($resourceName, 'Resource')) {
            $resourceName = substr($resourceName, 0, -8);
        }

        $actionName = ucfirst($action);
        $timingName = ucfirst($timing);

        // Generate class names like: UserBeforeCreate, UserAfterCreate
        $className = $resourceName . $timingName . $actionName;

        // Try common namespaces
        $namespaces = [
            'App\\Extensions\\Filament\\',
            'App\\Extensions\\',
        ];

        foreach ($namespaces as $namespace) {
            $fullClass = $namespace . $className;
            if (class_exists($fullClass)) {
                return $fullClass;
            }
        }

        return null;
    }

    /**
     * Create an extension point instance.
     */
    protected static function createFilamentExtensionPoint(
        string $extensionPointClass,
        ?object $record,
        array $data,
    ): ExtensionPointContract {
        return new $extensionPointClass($record, $data);
    }

    /**
     * Hook into the create action.
     */
    protected function beforeCreate(): void
    {
        static::dispatchBeforeAction('create', null, $this->form->getState());
    }

    /**
     * Hook after create action.
     */
    protected function afterCreate(): void
    {
        static::dispatchAfterAction('create', $this->record, $this->form->getState());
    }

    /**
     * Hook into the edit/update action.
     */
    protected function beforeSave(): void
    {
        static::dispatchBeforeAction('save', $this->record, $this->form->getState());
    }

    /**
     * Hook after edit/update action.
     */
    protected function afterSave(): void
    {
        static::dispatchAfterAction('save', $this->record, $this->form->getState());
    }

    /**
     * Hook into the delete action.
     */
    protected function beforeDelete(): void
    {
        static::dispatchBeforeAction('delete', $this->record);
    }

    /**
     * Hook after delete action.
     */
    protected function afterDelete(): void
    {
        static::dispatchAfterAction('delete', $this->record);
    }
}
