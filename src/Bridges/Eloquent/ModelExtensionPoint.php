<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Bridges\Eloquent;

use Esegments\LaravelExtensions\Contracts\ExtensionPointContract;
use Illuminate\Database\Eloquent\Model;

/**
 * Generic extension point for Eloquent model events.
 */
class ModelExtensionPoint implements ExtensionPointContract
{
    public function __construct(
        public readonly Model $model,
        public readonly string $event,
    ) {}

    /**
     * Get the model class name.
     */
    public function modelClass(): string
    {
        return $this->model::class;
    }

    /**
     * Get the model key.
     */
    public function modelKey(): mixed
    {
        return $this->model->getKey();
    }

    /**
     * Get changed attributes (for update events).
     *
     * @return array<string, mixed>
     */
    public function getChanges(): array
    {
        return $this->model->getChanges();
    }

    /**
     * Get dirty attributes (for updating events).
     *
     * @return array<string, mixed>
     */
    public function getDirty(): array
    {
        return $this->model->getDirty();
    }

    /**
     * Check if an attribute was changed.
     */
    public function wasChanged(string $attribute): bool
    {
        return $this->model->wasChanged($attribute);
    }
}
