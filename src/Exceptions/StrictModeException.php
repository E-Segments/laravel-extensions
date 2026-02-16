<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Exceptions;

use Esegments\Core\Exceptions\CoreException;

/**
 * Exception thrown in strict mode when dispatching to unregistered extension points.
 *
 * Strict mode helps catch configuration errors during development by ensuring
 * all extension points have at least one registered handler.
 */
final class StrictModeException extends CoreException
{
    protected ?string $errorCode = 'EXTENSION_STRICT_MODE';

    /**
     * Create an exception for an unregistered extension point.
     *
     * @param  class-string  $extensionPointClass
     */
    public static function unregisteredExtensionPoint(string $extensionPointClass): self
    {
        return new self(
            message: "Strict mode: No handlers registered for extension point [{$extensionPointClass}]",
            errorCode: 'EXTENSION_STRICT_MODE',
            context: ['extension_point' => $extensionPointClass],
        );
    }

    /**
     * Create an exception for an unknown handler.
     *
     * @param  class-string  $handlerClass
     */
    public static function unknownHandler(string $handlerClass): self
    {
        return new self(
            message: "Strict mode: Handler class [{$handlerClass}] does not exist",
            context: ['handler_class' => $handlerClass],
        );
    }

    /**
     * Create an exception for an invalid extension point.
     *
     * @param  class-string  $extensionPointClass
     */
    public static function invalidExtensionPoint(string $extensionPointClass): self
    {
        return new self(
            message: "Strict mode: Class [{$extensionPointClass}] does not implement ExtensionPointContract",
            context: ['extension_point' => $extensionPointClass],
        );
    }
}
