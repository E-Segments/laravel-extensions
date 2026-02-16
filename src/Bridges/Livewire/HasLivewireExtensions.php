<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Bridges\Livewire;

use Esegments\LaravelExtensions\Contracts\ExtensionPointContract;
use Esegments\LaravelExtensions\ExtensionDispatcher;

/**
 * Trait to add extension point dispatching to Livewire components.
 *
 * @example
 * ```php
 * class CreatePost extends Component
 * {
 *     use HasLivewireExtensions;
 *
 *     // Dispatches: CreatePostMounting, CreatePostRendering
 * }
 * ```
 */
trait HasLivewireExtensions
{
    /**
     * Boot the trait during component mounting.
     */
    public function bootHasLivewireExtensions(): void
    {
        if (! config('extensions.bridges.livewire', false)) {
            return;
        }

        $this->dispatchLivewireExtensionPoint('mounting');
    }

    /**
     * Hook into render.
     */
    public function renderingHasLivewireExtensions(): void
    {
        if (! config('extensions.bridges.livewire', false)) {
            return;
        }

        $this->dispatchLivewireExtensionPoint('rendering');
    }

    /**
     * Hook into updated lifecycle.
     */
    public function updatedHasLivewireExtensions(): void
    {
        if (! config('extensions.bridges.livewire', false)) {
            return;
        }

        $this->dispatchLivewireExtensionPoint('updated');
    }

    /**
     * Dispatch an extension point for a Livewire lifecycle event.
     */
    protected function dispatchLivewireExtensionPoint(string $event): void
    {
        $extensionPointClass = $this->getLivewireExtensionPointClass($event);

        if ($extensionPointClass === null || ! class_exists($extensionPointClass)) {
            return;
        }

        $extensionPoint = $this->createLivewireExtensionPoint($extensionPointClass);

        app(ExtensionDispatcher::class)->dispatch($extensionPoint);
    }

    /**
     * Get the extension point class for a lifecycle event.
     *
     * @return class-string|null
     */
    protected function getLivewireExtensionPointClass(string $event): ?string
    {
        $componentName = class_basename(static::class);
        $eventName = ucfirst($event);
        $className = $componentName . $eventName;

        // Try common namespaces
        $namespaces = [
            'App\\Extensions\\Livewire\\',
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
    protected function createLivewireExtensionPoint(string $extensionPointClass): ExtensionPointContract
    {
        return new $extensionPointClass($this);
    }
}
