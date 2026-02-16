<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Profiling;

use Esegments\LaravelExtensions\Contracts\ExtensionPointContract;
use Illuminate\Support\Facades\Log;

/**
 * Profiles extension point execution for performance analysis.
 *
 * @example
 * ```php
 * $profiler = new ExecutionProfiler();
 *
 * // Start profiling
 * $result = $profiler->start($extensionPoint);
 *
 * // Record handler executions
 * $startTime = microtime(true);
 * // ... execute handler ...
 * $profiler->recordHandler($result, new HandlerProfile(...));
 *
 * // Complete profiling
 * $profiler->complete($result);
 *
 * // Analyze
 * if ($result->hasSlow()) {
 *     Log::warning('Slow handlers detected', $result->toArray());
 * }
 * ```
 */
final class ExecutionProfiler
{
    private bool $enabled;

    private float $slowThreshold;

    private ?string $logChannel;

    /**
     * Last profile result.
     */
    private ?ProfileResult $lastProfile = null;

    public function __construct(
        ?bool $enabled = null,
        ?float $slowThreshold = null,
        ?string $logChannel = null,
    ) {
        $this->enabled = $enabled ?? (bool) config('extensions.profiling.enabled', false);
        $this->slowThreshold = $slowThreshold ?? (float) config('extensions.profiling.slow_threshold', 100);
        $this->logChannel = $logChannel ?? config('extensions.profiling.log_channel', 'extensions');
    }

    /**
     * Check if profiling is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Enable profiling.
     *
     * @return $this
     */
    public function enable(): self
    {
        $this->enabled = true;

        return $this;
    }

    /**
     * Disable profiling.
     *
     * @return $this
     */
    public function disable(): self
    {
        $this->enabled = false;

        return $this;
    }

    /**
     * Start profiling an extension point dispatch.
     */
    public function start(ExtensionPointContract $extensionPoint): ProfileResult
    {
        return new ProfileResult(
            extensionPointClass: $extensionPoint::class,
            startTime: microtime(true),
        );
    }

    /**
     * Record a handler execution.
     */
    public function recordHandler(ProfileResult $result, HandlerProfile $profile): void
    {
        $result->recordHandler($profile);
    }

    /**
     * Complete profiling and log if necessary.
     */
    public function complete(ProfileResult $result): ProfileResult
    {
        $result->complete();
        $this->lastProfile = $result;

        // Log slow handlers
        if ($this->enabled && $result->hasSlow($this->slowThreshold)) {
            $this->logSlowHandlers($result);
        }

        return $result;
    }

    /**
     * Get the last profile result.
     */
    public function getLastProfile(): ?ProfileResult
    {
        return $this->lastProfile;
    }

    /**
     * Get the slow threshold in milliseconds.
     */
    public function getSlowThreshold(): float
    {
        return $this->slowThreshold;
    }

    /**
     * Set the slow threshold in milliseconds.
     *
     * @return $this
     */
    public function setSlowThreshold(float $threshold): self
    {
        $this->slowThreshold = $threshold;

        return $this;
    }

    /**
     * Profile a callback.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return array{result: T, profile: HandlerProfile}
     */
    public function profileCallback(string $name, callable $callback): array
    {
        $memoryBefore = memory_get_usage(true);
        $startTime = microtime(true);
        $error = null;
        $result = null;

        try {
            $result = $callback();
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            throw $e;
        } finally {
            $executionTime = (microtime(true) - $startTime) * 1000;
            $memoryUsed = memory_get_usage(true) - $memoryBefore;
        }

        $profile = new HandlerProfile(
            handlerClass: $name,
            executionTimeMs: $executionTime,
            result: $result,
            error: $error,
            memoryUsageBytes: $memoryUsed,
        );

        return [
            'result' => $result,
            'profile' => $profile,
        ];
    }

    /**
     * Log slow handlers.
     */
    private function logSlowHandlers(ProfileResult $result): void
    {
        $slowHandlers = $result->slowHandlers($this->slowThreshold);

        $logger = $this->logChannel
            ? Log::channel($this->logChannel)
            : Log::getFacadeRoot();

        $logger->warning('[Extensions] Slow handlers detected', [
            'extension_point' => $result->extensionPointClass,
            'total_time_ms' => $result->totalTime(),
            'slow_handlers' => $slowHandlers->map(fn (HandlerProfile $p) => [
                'handler' => $p->handlerClass,
                'time_ms' => $p->executionTimeMs,
            ])->all(),
        ]);
    }
}
