<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Results;

use Esegments\Core\Concerns\Makeable;
use Esegments\LaravelExtensions\Contracts\ExtensionPointContract;
use Esegments\LaravelExtensions\Support\DebugInfo;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Result object for extension point dispatches.
 *
 * Collects results and errors from handler executions, providing
 * a structured way to analyze dispatch outcomes.
 *
 * @template T of ExtensionPointContract
 */
final class DispatchResult
{
    use Makeable;

    /**
     * Handler results indexed by handler class.
     *
     * @var array<string, mixed>
     */
    private array $results = [];

    /**
     * Errors caught during execution indexed by handler class.
     *
     * @var array<string, Throwable>
     */
    private array $errors = [];

    /**
     * Handlers that completed successfully.
     *
     * @var array<string>
     */
    private array $successful = [];

    /**
     * Handlers that were skipped.
     *
     * @var array<string, string>
     */
    private array $skipped = [];

    /**
     * @param  T  $extensionPoint
     */
    public function __construct(
        private readonly ExtensionPointContract $extensionPoint,
        private readonly ?DebugInfo $debugInfo = null,
        private readonly bool $wasInterrupted = false,
        private readonly ?string $interruptedBy = null,
    ) {}

    /**
     * Record a successful handler result.
     */
    public function recordSuccess(string $handlerClass, mixed $result): self
    {
        $this->results[$handlerClass] = $result;
        $this->successful[] = $handlerClass;

        return $this;
    }

    /**
     * Record a handler error.
     */
    public function recordError(string $handlerClass, Throwable $error): self
    {
        $this->errors[$handlerClass] = $error;

        return $this;
    }

    /**
     * Record a skipped handler.
     */
    public function recordSkipped(string $handlerClass, string $reason): self
    {
        $this->skipped[$handlerClass] = $reason;

        return $this;
    }

    /**
     * Get the extension point instance.
     *
     * @return T
     */
    public function extension(): ExtensionPointContract
    {
        return $this->extensionPoint;
    }

    /**
     * Get all handler results.
     *
     * @return Collection<string, mixed>
     */
    public function results(): Collection
    {
        return collect($this->results);
    }

    /**
     * Get all caught errors.
     *
     * @return Collection<string, Throwable>
     */
    public function errors(): Collection
    {
        return collect($this->errors);
    }

    /**
     * Get all successfully completed handlers.
     *
     * @return Collection<int, string>
     */
    public function successful(): Collection
    {
        return collect($this->successful);
    }

    /**
     * Get all skipped handlers with reasons.
     *
     * @return Collection<string, string>
     */
    public function skipped(): Collection
    {
        return collect($this->skipped);
    }

    /**
     * Check if any errors occurred.
     */
    public function hasErrors(): bool
    {
        return ! empty($this->errors);
    }

    /**
     * Check if dispatch completed without errors.
     */
    public function isSuccessful(): bool
    {
        return empty($this->errors) && ! $this->wasInterrupted;
    }

    /**
     * Check if the dispatch was interrupted.
     */
    public function wasInterrupted(): bool
    {
        return $this->wasInterrupted;
    }

    /**
     * Get the handler that interrupted the dispatch.
     */
    public function interruptedBy(): ?string
    {
        return $this->interruptedBy;
    }

    /**
     * Get the debug info if available.
     */
    public function debug(): ?DebugInfo
    {
        return $this->debugInfo;
    }

    /**
     * Get total execution time in milliseconds.
     */
    public function totalTime(): ?float
    {
        return $this->debugInfo?->getTotalTime();
    }

    /**
     * Get the first result value.
     */
    public function firstResult(): mixed
    {
        return $this->results()->first();
    }

    /**
     * Get a specific handler's result.
     */
    public function resultFor(string $handlerClass): mixed
    {
        return $this->results[$handlerClass] ?? null;
    }

    /**
     * Get a specific handler's error.
     */
    public function errorFor(string $handlerClass): ?Throwable
    {
        return $this->errors[$handlerClass] ?? null;
    }

    /**
     * Throw the first error if any occurred.
     *
     * @throws Throwable
     */
    public function throwOnError(): self
    {
        if ($this->hasErrors()) {
            throw $this->errors()->first();
        }

        return $this;
    }

    /**
     * Convert to array representation.
     *
     * @return array{
     *     extension_class: string,
     *     successful: array<string>,
     *     errors: array<string, string>,
     *     skipped: array<string, string>,
     *     was_interrupted: bool,
     *     interrupted_by: ?string,
     *     total_time_ms: ?float
     * }
     */
    public function toArray(): array
    {
        return [
            'extension_class' => $this->extensionPoint::class,
            'successful' => $this->successful,
            'errors' => collect($this->errors)->map(fn (Throwable $e) => $e->getMessage())->all(),
            'skipped' => $this->skipped,
            'was_interrupted' => $this->wasInterrupted,
            'interrupted_by' => $this->interruptedBy,
            'total_time_ms' => $this->totalTime(),
        ];
    }
}
