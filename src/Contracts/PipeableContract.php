<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Contracts;

/**
 * Marker interface for extension points that support data transformation.
 *
 * Pipeable extension points allow handlers to modify the extension point's
 * data as it passes through the handler chain. This is similar to Laravel's
 * Pipeline pattern.
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
 * // Handler 1: Apply discount
 * public function handle(ExtensionPointContract $ext): mixed
 * {
 *     $ext->price *= 0.9; // 10% discount
 *     return null;
 * }
 *
 * // Handler 2: Apply tax
 * public function handle(ExtensionPointContract $ext): mixed
 * {
 *     $ext->price *= 1.1; // 10% tax
 *     return null;
 * }
 * ```
 */
interface PipeableContract extends ExtensionPointContract
{
    // Marker interface - pipeable extension points are mutable
    // Handlers modify the object directly
}
