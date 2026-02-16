<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Exceptions;

use Esegments\Core\Exceptions\CoreException;
use Esegments\LaravelExtensions\Contracts\ExtensionPointContract;

/**
 * Base exception for extension point errors.
 */
class ExtensionException extends CoreException
{
    protected ?string $errorCode = 'EXTENSION_ERROR';

    /**
     * Create an exception for an invalid extension point.
     *
     * @param  class-string  $class
     */
    public static function invalidExtensionPoint(string $class): static
    {
        return new static(
            message: "Class [{$class}] must implement ".ExtensionPointContract::class,
            errorCode: 'INVALID_EXTENSION_POINT',
            context: ['class' => $class],
        );
    }

    /**
     * Create an exception for a handler error.
     */
    public static function handlerFailed(string $handler, string $reason): static
    {
        return new static(
            message: "Handler [{$handler}] failed: {$reason}",
            errorCode: 'HANDLER_FAILED',
            context: ['handler' => $handler, 'reason' => $reason],
        );
    }

    /**
     * Create an exception for discovery errors.
     */
    public static function discoveryFailed(string $directory, string $reason): static
    {
        return new static(
            message: "Failed to discover handlers in [{$directory}]: {$reason}",
            errorCode: 'DISCOVERY_FAILED',
            context: ['directory' => $directory, 'reason' => $reason],
        );
    }
}
