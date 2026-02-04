<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Contracts;

/**
 * Marker interface for extension points.
 *
 * Extension points are typed PHP classes that define specific points in your
 * application where handlers can be registered to modify behavior or data.
 *
 * Unlike WordPress-style string hooks, extension points provide:
 * - Full IDE auto-completion
 * - Type safety for handlers
 * - Explicit, predictable registration
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
    // Marker interface - extension points define their own properties and methods
}
