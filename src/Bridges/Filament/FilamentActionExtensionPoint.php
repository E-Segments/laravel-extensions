<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Bridges\Filament;

use Esegments\LaravelExtensions\Contracts\ExtensionPointContract;

/**
 * Extension point for Filament resource actions.
 */
class FilamentActionExtensionPoint implements ExtensionPointContract
{
    public function __construct(
        public readonly string $resourceClass,
        public readonly string $action,
        public readonly ?object $record = null,
        public readonly array $data = [],
    ) {}

    /**
     * Get the resource class name.
     */
    public function resourceClass(): string
    {
        return $this->resourceClass;
    }

    /**
     * Get the action name.
     */
    public function action(): string
    {
        return $this->action;
    }

    /**
     * Get the record being acted upon.
     */
    public function record(): ?object
    {
        return $this->record;
    }

    /**
     * Get the action data.
     *
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return $this->data;
    }

    /**
     * Get the record key if available.
     */
    public function recordKey(): mixed
    {
        if ($this->record === null) {
            return null;
        }

        return method_exists($this->record, 'getKey')
            ? $this->record->getKey()
            : null;
    }
}
