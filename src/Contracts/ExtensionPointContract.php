<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Contracts;

/**
 * Marker interface for extension points.
 *
 * Extension points are type-safe PHP classes that define extensible moments
 * in your application. Unlike WordPress-style string hooks, extension points
 * provide full IDE support and type safety.
 *
 * @example
 * ```php
 * final class ValidateOrderExtension implements ExtensionPointContract
 * {
 *     public function __construct(
 *         public readonly Order $order,
 *         public readonly Customer $customer,
 *     ) {}
 * }
 * ```
 */
interface ExtensionPointContract
{
    // Marker interface - extension points define their own properties
}
