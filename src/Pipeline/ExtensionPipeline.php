<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Pipeline;

use Closure;
use Esegments\LaravelExtensions\Contracts\ExtensionPointContract;
use Esegments\LaravelExtensions\Contracts\PipelineContract;
use Illuminate\Pipeline\Pipeline;
use Throwable;

/**
 * Pipeline for chaining extension handlers.
 *
 * @example
 * ```php
 * $result = Extensions::pipeline(new ProcessOrder($order))
 *     ->through([
 *         ValidateInventory::class,
 *         ApplyDiscounts::class,
 *         CalculateTax::class,
 *     ])
 *     ->onFailure(fn ($e) => Log::error('Pipeline failed', ['error' => $e]))
 *     ->run();
 * ```
 */
class ExtensionPipeline
{
    /**
     * @var array<class-string|callable>
     */
    protected array $pipes = [];

    protected ?Closure $onFailure = null;

    protected ?Closure $onSuccess = null;

    protected bool $stopOnFailure = true;

    public function __construct(
        protected ExtensionPointContract $extensionPoint,
    ) {}

    /**
     * Create a new pipeline for an extension point.
     */
    public static function for(ExtensionPointContract $extensionPoint): static
    {
        return new static($extensionPoint);
    }

    /**
     * Set the pipes (handlers) to run.
     *
     * @param  array<class-string|callable>  $pipes
     */
    public function through(array $pipes): static
    {
        $this->pipes = $pipes;

        return $this;
    }

    /**
     * Add a pipe to the pipeline.
     *
     * @param  class-string|callable  $pipe
     */
    public function pipe(string|callable $pipe): static
    {
        $this->pipes[] = $pipe;

        return $this;
    }

    /**
     * Set the failure callback.
     */
    public function onFailure(Closure $callback): static
    {
        $this->onFailure = $callback;

        return $this;
    }

    /**
     * Set the success callback.
     */
    public function onSuccess(Closure $callback): static
    {
        $this->onSuccess = $callback;

        return $this;
    }

    /**
     * Continue execution even if a pipe fails.
     */
    public function continueOnFailure(): static
    {
        $this->stopOnFailure = false;

        return $this;
    }

    /**
     * Run the pipeline.
     *
     * @return mixed The final result after all pipes have processed
     */
    public function run(): mixed
    {
        $passable = $this->extensionPoint instanceof PipelineContract
            ? $this->extensionPoint->getPipelineData()
            : $this->extensionPoint;

        try {
            $result = app(Pipeline::class)
                ->send($passable)
                ->through($this->buildPipes())
                ->then(fn ($passable) => $passable);

            if ($this->onSuccess !== null) {
                ($this->onSuccess)($result);
            }

            // Update the extension point if it's a pipeline contract
            if ($this->extensionPoint instanceof PipelineContract) {
                $this->extensionPoint->setPipelineData($result);
            }

            return $result;
        } catch (Throwable $exception) {
            if ($this->onFailure !== null) {
                return ($this->onFailure)($exception, $passable);
            }

            throw $exception;
        }
    }

    /**
     * Build the pipe closures.
     *
     * @return array<Closure>
     */
    protected function buildPipes(): array
    {
        return array_map(function ($pipe) {
            if (is_callable($pipe)) {
                return $this->wrapCallable($pipe);
            }

            return $this->wrapClass($pipe);
        }, $this->pipes);
    }

    /**
     * Wrap a callable pipe.
     */
    protected function wrapCallable(callable $pipe): Closure
    {
        return function ($passable, Closure $next) use ($pipe) {
            try {
                $result = $pipe($passable, $this->extensionPoint);

                return $next($result ?? $passable);
            } catch (Throwable $exception) {
                if ($this->stopOnFailure) {
                    throw $exception;
                }

                // Log error but continue
                if (config('extensions.debug', false)) {
                    logger()->warning('[Extensions] Pipeline pipe failed', [
                        'pipe' => 'callable',
                        'error' => $exception->getMessage(),
                    ]);
                }

                return $next($passable);
            }
        };
    }

    /**
     * Wrap a class pipe.
     *
     * @param  class-string  $pipeClass
     */
    protected function wrapClass(string $pipeClass): Closure
    {
        return function ($passable, Closure $next) use ($pipeClass) {
            try {
                $pipe = app($pipeClass);

                // Try different method signatures
                if (method_exists($pipe, 'handle')) {
                    $result = $pipe->handle($passable, $this->extensionPoint, $next);

                    // If handler returned via $next, result is already the next passable
                    if ($result !== null) {
                        return $result;
                    }

                    return $next($passable);
                }

                if (is_callable($pipe)) {
                    $result = $pipe($passable, $this->extensionPoint);

                    return $next($result ?? $passable);
                }

                // No valid handler method found
                return $next($passable);
            } catch (Throwable $exception) {
                if ($this->stopOnFailure) {
                    throw $exception;
                }

                // Log error but continue
                if (config('extensions.debug', false)) {
                    logger()->warning('[Extensions] Pipeline pipe failed', [
                        'pipe' => $pipeClass,
                        'error' => $exception->getMessage(),
                    ]);
                }

                return $next($passable);
            }
        };
    }
}
