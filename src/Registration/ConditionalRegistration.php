<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Registration;

use Closure;
use Esegments\LaravelExtensions\Contracts\ExtensionPointContract;
use Esegments\LaravelExtensions\HandlerRegistry;

/**
 * Fluent API for conditional handler registration.
 *
 * @example
 * ```php
 * // Register only in production
 * $registration->when(app()->environment('production'))
 *     ->register(UserCreated::class, ProductionAuditHandler::class);
 *
 * // Register based on feature flag
 * $registration->when(fn () => Feature::active('new-billing'))
 *     ->register(OrderPlaced::class, NewBillingHandler::class);
 * ```
 */
final class ConditionalRegistration
{
    /**
     * @var bool|Closure(): bool
     */
    private bool|Closure $condition;

    private bool $evaluated = false;

    private bool $conditionResult = false;

    public function __construct(
        private readonly HandlerRegistry $registry,
        bool|Closure $condition,
    ) {
        $this->condition = $condition;
    }

    /**
     * Register a handler if the condition is satisfied.
     *
     * @param  class-string<ExtensionPointContract>  $extensionPointClass
     * @param  string|callable  $handler
     * @return $this
     */
    public function register(
        string $extensionPointClass,
        string|callable $handler,
        int $priority = 100,
    ): self {
        if ($this->shouldRegister()) {
            $this->registry->register($extensionPointClass, $handler, $priority);
        }

        return $this;
    }

    /**
     * Register multiple handlers if the condition is satisfied.
     *
     * @param  array<array{0: class-string<ExtensionPointContract>, 1: string|callable, 2?: int}>  $handlers
     * @return $this
     */
    public function registerMany(array $handlers): self
    {
        if (! $this->shouldRegister()) {
            return $this;
        }

        foreach ($handlers as $handlerDef) {
            $this->registry->register(
                $handlerDef[0],
                $handlerDef[1],
                $handlerDef[2] ?? 100
            );
        }

        return $this;
    }

    /**
     * Register a group if the condition is satisfied.
     *
     * @param  array<array{0: class-string<ExtensionPointContract>, 1: string|callable, 2?: int}>  $handlers
     * @return $this
     */
    public function registerGroup(string $groupName, array $handlers): self
    {
        if ($this->shouldRegister()) {
            $this->registry->registerGroup($groupName, $handlers);
        }

        return $this;
    }

    /**
     * Evaluate and cache the condition result.
     */
    private function shouldRegister(): bool
    {
        if (! $this->evaluated) {
            $this->conditionResult = $this->condition instanceof Closure
                ? ($this->condition)()
                : $this->condition;
            $this->evaluated = true;
        }

        return $this->conditionResult;
    }
}
