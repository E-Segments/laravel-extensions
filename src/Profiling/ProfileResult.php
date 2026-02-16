<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Profiling;

use Esegments\Core\Concerns\Makeable;
use Illuminate\Support\Collection;

/**
 * Complete profiling result for an extension point dispatch.
 */
final class ProfileResult
{
    use Makeable;

    /**
     * @var array<HandlerProfile>
     */
    private array $handlers = [];

    public function __construct(
        public readonly string $extensionPointClass,
        public readonly float $startTime,
        private float $endTime = 0,
        private float $memoryStart = 0,
        private float $memoryEnd = 0,
    ) {
        $this->memoryStart = memory_get_usage(true);
    }

    /**
     * Record a handler execution.
     */
    public function recordHandler(HandlerProfile $profile): self
    {
        $this->handlers[] = $profile;

        return $this;
    }

    /**
     * Mark profiling as complete.
     */
    public function complete(): self
    {
        $this->endTime = microtime(true);
        $this->memoryEnd = memory_get_usage(true);

        return $this;
    }

    /**
     * Get total execution time in milliseconds.
     */
    public function totalTime(): float
    {
        return ($this->endTime - $this->startTime) * 1000;
    }

    /**
     * Get memory usage delta in bytes.
     */
    public function memoryDelta(): float
    {
        return $this->memoryEnd - $this->memoryStart;
    }

    /**
     * Get memory peak during execution.
     */
    public function memoryPeak(): float
    {
        return memory_get_peak_usage(true);
    }

    /**
     * Get all handler profiles.
     *
     * @return Collection<int, HandlerProfile>
     */
    public function handlers(): Collection
    {
        return collect($this->handlers);
    }

    /**
     * Get the slowest handler.
     */
    public function slowest(): ?HandlerProfile
    {
        return $this->handlers()
            ->filter(fn (HandlerProfile $p) => ! $p->skipped)
            ->sortByDesc(fn (HandlerProfile $p) => $p->executionTimeMs)
            ->first();
    }

    /**
     * Get all slow handlers.
     *
     * @return Collection<int, HandlerProfile>
     */
    public function slowHandlers(float $thresholdMs = 100): Collection
    {
        return $this->handlers()
            ->filter(fn (HandlerProfile $p) => $p->isSlow($thresholdMs));
    }

    /**
     * Get handlers that failed.
     *
     * @return Collection<int, HandlerProfile>
     */
    public function failedHandlers(): Collection
    {
        return $this->handlers()
            ->filter(fn (HandlerProfile $p) => $p->error !== null);
    }

    /**
     * Get handlers that were skipped.
     *
     * @return Collection<int, HandlerProfile>
     */
    public function skippedHandlers(): Collection
    {
        return $this->handlers()
            ->filter(fn (HandlerProfile $p) => $p->skipped);
    }

    /**
     * Get formatted total time.
     */
    public function formattedTotalTime(): string
    {
        $time = $this->totalTime();

        if ($time < 1) {
            return sprintf('%.2fμs', $time * 1000);
        }

        if ($time < 1000) {
            return sprintf('%.2fms', $time);
        }

        return sprintf('%.2fs', $time / 1000);
    }

    /**
     * Get formatted memory usage.
     */
    public function formattedMemory(): string
    {
        $bytes = $this->memoryDelta();

        if ($bytes < 0) {
            return '0B';
        }

        if ($bytes < 1024) {
            return round($bytes) . 'B';
        }

        if ($bytes < 1048576) {
            return sprintf('%.2fKB', $bytes / 1024);
        }

        return sprintf('%.2fMB', $bytes / 1048576);
    }

    /**
     * Check if there are any slow handlers.
     */
    public function hasSlow(float $thresholdMs = 100): bool
    {
        return $this->slowHandlers($thresholdMs)->isNotEmpty();
    }

    /**
     * Check if there are any errors.
     */
    public function hasErrors(): bool
    {
        return $this->failedHandlers()->isNotEmpty();
    }

    /**
     * Convert to array.
     *
     * @return array{
     *     extension_point: string,
     *     total_time_ms: float,
     *     memory_delta_bytes: float,
     *     memory_peak_bytes: float,
     *     handler_count: int,
     *     slow_count: int,
     *     error_count: int,
     *     handlers: array
     * }
     */
    public function toArray(): array
    {
        return [
            'extension_point' => $this->extensionPointClass,
            'total_time_ms' => $this->totalTime(),
            'memory_delta_bytes' => $this->memoryDelta(),
            'memory_peak_bytes' => $this->memoryPeak(),
            'handler_count' => count($this->handlers),
            'slow_count' => $this->slowHandlers()->count(),
            'error_count' => $this->failedHandlers()->count(),
            'handlers' => $this->handlers()->map->toArray()->all(),
        ];
    }
}
