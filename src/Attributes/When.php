<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Attributes;

use Attribute;

/**
 * Attribute to conditionally register a handler based on environment.
 *
 * @example
 * ```php
 * #[ExtensionHandler(UserCreated::class)]
 * #[When('production')]
 * class ProductionOnlyHandler { }
 *
 * #[ExtensionHandler(UserCreated::class)]
 * #[When(['staging', 'production'])]
 * class NonLocalHandler { }
 * ```
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class When
{
    /**
     * @param  string|array<string>  $environments  Environment(s) where handler should be active
     */
    public function __construct(
        public readonly string|array $environments,
    ) {}

    /**
     * Check if the condition is met.
     */
    public function isSatisfied(): bool
    {
        $environments = is_array($this->environments)
            ? $this->environments
            : [$this->environments];

        return app()->environment($environments);
    }
}
