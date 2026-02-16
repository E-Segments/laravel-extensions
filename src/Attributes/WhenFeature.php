<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Attributes;

use Attribute;

/**
 * Attribute to conditionally register a handler based on feature flags.
 *
 * Integrates with Laravel Pennant or any custom feature flag implementation.
 *
 * @example
 * ```php
 * #[ExtensionHandler(UserCreated::class)]
 * #[WhenFeature('new-billing')]
 * class NewBillingHandler { }
 *
 * #[ExtensionHandler(UserCreated::class)]
 * #[WhenFeature('analytics', active: false)]
 * class LegacyAnalyticsHandler { }
 * ```
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class WhenFeature
{
    /**
     * @param  string  $feature  Feature flag name
     * @param  bool  $active  Whether the feature should be active (true) or inactive (false)
     * @param  mixed  $scope  Optional scope for the feature check (user, team, etc.)
     */
    public function __construct(
        public readonly string $feature,
        public readonly bool $active = true,
        public readonly mixed $scope = null,
    ) {}

    /**
     * Check if the condition is met.
     */
    public function isSatisfied(): bool
    {
        // Try Laravel Pennant first
        if (class_exists(\Laravel\Pennant\Feature::class)) {
            $isActive = $this->scope !== null
                ? \Laravel\Pennant\Feature::for($this->scope)->active($this->feature)
                : \Laravel\Pennant\Feature::active($this->feature);

            return $this->active ? $isActive : ! $isActive;
        }

        // Fallback: check config
        $isActive = (bool) config("features.{$this->feature}", false);

        return $this->active ? $isActive : ! $isActive;
    }
}
