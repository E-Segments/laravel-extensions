<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Contracts;

/**
 * Marker for extension points that allow data transformation.
 *
 * Pipeable extension points indicate that handlers may modify the
 * extension point's data. Each handler receives the extension point,
 * can modify its properties, and the next handler receives the
 * modified state.
 *
 * This is similar to Laravel's Pipeline, but type-safe and explicit.
 *
 * @example
 * ```php
 * final class CalculatePriceExtension implements PipeableContract
 * {
 *     public function __construct(
 *         public readonly Product $product,
 *         public float $price,
 *     ) {}
 * }
 *
 * // Handlers modify the price in sequence:
 * // Handler 1: Apply bulk discount -> $ext->price *= 0.9
 * // Handler 2: Apply tax -> $ext->price *= 1.1
 * // Handler 3: Round to cents -> $ext->price = round($ext->price, 2)
 * ```
 */
interface PipeableContract extends ExtensionPointContract
{
    // Marker interface - pipeable extension points are mutable
    // Handlers modify the object directly
}
