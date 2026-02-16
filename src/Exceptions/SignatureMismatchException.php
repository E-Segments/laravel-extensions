<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Exceptions;

use Esegments\Core\Exceptions\CoreException;

/**
 * Exception thrown when a handler's signature doesn't match the extension point's requirements.
 */
final class SignatureMismatchException extends CoreException
{
    protected ?string $errorCode = 'HANDLER_SIGNATURE_MISMATCH';

    /**
     * Create an exception for a signature mismatch.
     */
    public static function mismatch(
        string $handlerClass,
        string $extensionPointClass,
        string $expected,
        string $actual
    ): self {
        return new self(
            message: "Handler [{$handlerClass}] signature mismatch for [{$extensionPointClass}]. Expected: {$expected}, got: {$actual}",
            context: [
                'handler' => $handlerClass,
                'extension_point' => $extensionPointClass,
                'expected' => $expected,
                'actual' => $actual,
            ],
        );
    }

    /**
     * Create an exception for a missing handle method.
     */
    public static function missingHandleMethod(string $handlerClass): self
    {
        return new self(
            message: "Handler [{$handlerClass}] must have a handle() method or be invokable",
            context: ['handler' => $handlerClass],
        );
    }
}
