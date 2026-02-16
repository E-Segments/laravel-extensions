<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Contracts;

/**
 * Contract for pipeline extension points.
 *
 * Extension points implementing this interface will have their handlers
 * executed as a pipeline, where each handler can transform the data
 * and pass it to the next handler.
 */
interface PipelineContract extends ExtensionPointContract
{
    /**
     * Get the data to be piped through handlers.
     */
    public function getPipelineData(): mixed;

    /**
     * Set the transformed data.
     */
    public function setPipelineData(mixed $data): void;
}
