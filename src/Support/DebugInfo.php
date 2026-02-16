<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Support;

use Esegments\Core\Concerns\Makeable;
use Esegments\Core\Contracts\Arrayable;
use Esegments\LaravelExtensions\Contracts\ExtensionPointContract;
use Esegments\LaravelExtensions\Contracts\InterruptibleContract;
use JsonSerializable;

/**
 * Debug information for an extension point dispatch.
 *
 * @implements Arrayable<string, mixed>
 */
final class DebugInfo implements Arrayable, JsonSerializable
{
    use Makeable;
    /**
     * @var array<array{handler: string, duration_ms: float, result: mixed, error: ?string}>
     */
    public array $handlerExecutions = [];

    public float $totalDurationMs = 0;

    public bool $wasInterrupted = false;

    public ?string $interruptedBy = null;

    public function __construct(
        public readonly string $extensionPointClass,
        public readonly float $startTime,
    ) {}

    /**
     * Record a handler execution.
     */
    public function recordHandler(
        string $handlerClass,
        float $durationMs,
        mixed $result,
        ?string $error = null,
    ): void {
        $this->handlerExecutions[] = [
            'handler' => $handlerClass,
            'duration_ms' => round($durationMs, 3),
            'result' => $this->serializeResult($result),
            'error' => $error,
        ];
    }

    /**
     * Mark the dispatch as complete.
     */
    public function complete(ExtensionPointContract $extensionPoint): void
    {
        $this->totalDurationMs = round((microtime(true) - $this->startTime) * 1000, 3);

        if ($extensionPoint instanceof InterruptibleContract) {
            $this->wasInterrupted = $extensionPoint->wasInterrupted();
            $this->interruptedBy = $extensionPoint->getInterruptedBy();
        }
    }

    /**
     * Convert to array for logging.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'extension_point' => $this->extensionPointClass,
            'total_duration_ms' => $this->totalDurationMs,
            'handler_count' => count($this->handlerExecutions),
            'was_interrupted' => $this->wasInterrupted,
            'interrupted_by' => $this->interruptedBy,
            'handlers' => $this->handlerExecutions,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Serialize a result for logging.
     */
    private function serializeResult(mixed $result): mixed
    {
        if ($result === null) {
            return null;
        }

        if (is_scalar($result)) {
            return $result;
        }

        if (is_array($result)) {
            return '[array]';
        }

        if (is_object($result)) {
            return '['.get_class($result).']';
        }

        return '[unknown]';
    }
}
