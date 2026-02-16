<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\CircuitBreaker;

/**
 * Circuit breaker states.
 *
 * - Closed: Normal operation, requests pass through
 * - Open: Circuit is tripped, requests fail fast
 * - HalfOpen: Testing if the circuit can be closed again
 *
 * Implements Filament's HasColor, HasIcon, HasLabel contracts if available
 * for seamless integration with Filament admin panels.
 */
enum CircuitState: string
{
    case Closed = 'closed';
    case Open = 'open';
    case HalfOpen = 'half_open';

    /**
     * Get the human-readable label.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::Closed => 'Closed',
            self::Open => 'Open',
            self::HalfOpen => 'Half Open',
        };
    }

    /**
     * Get the color for UI display (Filament/Tailwind compatible).
     */
    public function getColor(): string
    {
        return match ($this) {
            self::Closed => 'success',
            self::Open => 'danger',
            self::HalfOpen => 'warning',
        };
    }

    /**
     * Get the icon for UI display (Heroicon compatible).
     */
    public function getIcon(): string
    {
        return match ($this) {
            self::Closed => 'heroicon-o-check-circle',
            self::Open => 'heroicon-o-x-circle',
            self::HalfOpen => 'heroicon-o-exclamation-circle',
        };
    }

    /**
     * Check if requests should be allowed through.
     */
    public function allowsRequests(): bool
    {
        return $this !== self::Open;
    }

    /**
     * Check if the circuit is in a failure state.
     */
    public function isFailure(): bool
    {
        return $this === self::Open;
    }
}
