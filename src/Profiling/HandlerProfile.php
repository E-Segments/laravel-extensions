<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Profiling;

use Esegments\Core\Concerns\Makeable;

/**
 * Profile data for a single handler execution.
 */
final class HandlerProfile
{
    use Makeable;

    public function __construct(
        public readonly string $handlerClass,
        public readonly float $executionTimeMs,
        public readonly mixed $result,
        public readonly ?string $error = null,
        public readonly float $memoryUsageBytes = 0,
        public readonly bool $async = false,
        public readonly bool $skipped = false,
        public readonly ?string $skipReason = null,
    ) {}

    /**
     * Check if the handler execution was successful.
     */
    public function isSuccessful(): bool
    {
        return $this->error === null && ! $this->skipped;
    }

    /**
     * Check if the handler was slow (above threshold).
     */
    public function isSlow(float $thresholdMs = 100): bool
    {
        return $this->executionTimeMs > $thresholdMs;
    }

    /**
     * Get execution time formatted.
     */
    public function formattedTime(): string
    {
        if ($this->executionTimeMs < 1) {
            return sprintf('%.2fμs', $this->executionTimeMs * 1000);
        }

        if ($this->executionTimeMs < 1000) {
            return sprintf('%.2fms', $this->executionTimeMs);
        }

        return sprintf('%.2fs', $this->executionTimeMs / 1000);
    }

    /**
     * Get memory usage formatted.
     */
    public function formattedMemory(): string
    {
        $bytes = $this->memoryUsageBytes;

        if ($bytes < 1024) {
            return "{$bytes}B";
        }

        if ($bytes < 1048576) {
            return sprintf('%.2fKB', $bytes / 1024);
        }

        return sprintf('%.2fMB', $bytes / 1048576);
    }

    /**
     * Convert to array.
     *
     * @return array{
     *     handler: string,
     *     execution_time_ms: float,
     *     memory_bytes: float,
     *     successful: bool,
     *     error: ?string,
     *     async: bool,
     *     skipped: bool,
     *     skip_reason: ?string
     * }
     */
    public function toArray(): array
    {
        return [
            'handler' => $this->handlerClass,
            'execution_time_ms' => $this->executionTimeMs,
            'memory_bytes' => $this->memoryUsageBytes,
            'successful' => $this->isSuccessful(),
            'error' => $this->error,
            'async' => $this->async,
            'skipped' => $this->skipped,
            'skip_reason' => $this->skipReason,
        ];
    }
}
