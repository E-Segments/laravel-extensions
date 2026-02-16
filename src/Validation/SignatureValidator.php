<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Validation;

use Esegments\LaravelExtensions\Contracts\ExtensionHandlerContract;
use Esegments\LaravelExtensions\Contracts\ExtensionPointContract;
use Esegments\LaravelExtensions\Exceptions\SignatureMismatchException;
use ReflectionClass;
use ReflectionMethod;

/**
 * Validates handler signatures against extension point requirements.
 */
final class SignatureValidator
{
    /**
     * Validate a handler for an extension point.
     *
     * @param  class-string  $handlerClass
     * @param  class-string<ExtensionPointContract>  $extensionPointClass
     *
     * @throws SignatureMismatchException
     */
    public function validate(string $handlerClass, string $extensionPointClass): bool
    {
        if (! class_exists($handlerClass)) {
            return false;
        }

        $reflection = new ReflectionClass($handlerClass);

        // Check for handle method (ExtensionHandlerContract)
        if (is_a($handlerClass, ExtensionHandlerContract::class, true)) {
            return $this->validateHandleMethod($reflection, $extensionPointClass);
        }

        // Check for __invoke method (invokable class)
        if ($reflection->hasMethod('__invoke')) {
            return $this->validateInvokeMethod($reflection, $extensionPointClass);
        }

        throw SignatureMismatchException::missingHandleMethod($handlerClass);
    }

    /**
     * Validate the handle() method signature.
     *
     * @throws SignatureMismatchException
     */
    private function validateHandleMethod(ReflectionClass $reflection, string $extensionPointClass): bool
    {
        $method = $reflection->getMethod('handle');

        return $this->validateMethodSignature($method, $extensionPointClass, $reflection->getName());
    }

    /**
     * Validate the __invoke() method signature.
     *
     * @throws SignatureMismatchException
     */
    private function validateInvokeMethod(ReflectionClass $reflection, string $extensionPointClass): bool
    {
        $method = $reflection->getMethod('__invoke');

        return $this->validateMethodSignature($method, $extensionPointClass, $reflection->getName());
    }

    /**
     * Validate a method's parameter accepts the extension point.
     *
     * @throws SignatureMismatchException
     */
    private function validateMethodSignature(
        ReflectionMethod $method,
        string $extensionPointClass,
        string $handlerClass
    ): bool {
        $params = $method->getParameters();

        if (empty($params)) {
            throw SignatureMismatchException::mismatch(
                $handlerClass,
                $extensionPointClass,
                "({$extensionPointClass})",
                '()'
            );
        }

        $firstParam = $params[0];
        $type = $firstParam->getType();

        if ($type === null) {
            // No type hint, allow any
            return true;
        }

        $typeName = $type instanceof \ReflectionNamedType ? $type->getName() : '';

        // Check if the type matches or is a parent
        if ($typeName === $extensionPointClass) {
            return true;
        }

        // Check if extension point implements the type
        if (is_a($extensionPointClass, $typeName, true)) {
            return true;
        }

        // Check for ExtensionPointContract (generic handler)
        if ($typeName === ExtensionPointContract::class) {
            return true;
        }

        throw SignatureMismatchException::mismatch(
            $handlerClass,
            $extensionPointClass,
            $extensionPointClass,
            $typeName
        );
    }

    /**
     * Get expected signature for an extension point.
     *
     * @param  class-string<ExtensionPointContract>  $extensionPointClass
     */
    public function getExpectedSignature(string $extensionPointClass): string
    {
        $shortName = $this->getShortName($extensionPointClass);

        return "handle({$shortName} \$event): mixed";
    }

    /**
     * Get short class name.
     */
    private function getShortName(string $className): string
    {
        $parts = explode('\\', $className);

        return array_pop($parts);
    }
}
