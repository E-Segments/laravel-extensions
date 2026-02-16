<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Attributes;

use Attribute;
use Esegments\LaravelExtensions\Contracts\ExtensionPointContract;

/**
 * Attribute for auto-registering extension point handlers.
 *
 * When discovery is enabled, handlers marked with this attribute will be
 * automatically registered with the HandlerRegistry.
 *
 * @example
 * ```php
 * #[ExtensionHandler(ValidateOrderExtension::class, priority: 10)]
 * final class CheckInventoryHandler implements ExtensionHandlerContract
 * {
 *     public function handle(ExtensionPointContract $extensionPoint): mixed
 *     {
 *         // ...
 *     }
 * }
 * ```
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class ExtensionHandler
{
    /**
     * @param  class-string<ExtensionPointContract>  $extensionPoint  The extension point class to handle
     * @param  int  $priority  Handler priority (lower runs first, default: 100)
     * @param  bool  $async  Whether to run this handler asynchronously via queue
     * @param  string|null  $queue  Queue name for async handlers (null = default queue)
     */
    public function __construct(
        public readonly string $extensionPoint,
        public readonly int $priority = 100,
        public readonly bool $async = false,
        public readonly ?string $queue = null,
    ) {}
}
