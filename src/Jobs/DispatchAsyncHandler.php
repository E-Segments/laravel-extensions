<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Jobs;

use Esegments\LaravelExtensions\Attributes\Async;
use Esegments\LaravelExtensions\Contracts\ExtensionHandlerContract;
use Esegments\LaravelExtensions\Contracts\ExtensionPointContract;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use ReflectionClass;
use Throwable;

/**
 * Job for dispatching async extension handlers.
 *
 * This job wraps an async handler and extension point, executing
 * the handler when the job is processed by a queue worker.
 */
final class DispatchAsyncHandler implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array<int>|int
     */
    public array|int $backoff = 10;

    /**
     * The Async attribute configuration.
     */
    protected ?Async $asyncConfig = null;

    public function __construct(
        public readonly string $handlerClass,
        public readonly ExtensionPointContract $extensionPoint,
        ?string $queue = null,
        ?int $delay = null,
        ?int $tries = null,
        array|int|null $backoff = null,
        ?int $timeout = null,
    ) {
        // Load Async attribute configuration
        $this->asyncConfig = $this->loadAsyncConfig();

        // Apply configuration from attribute or parameters
        $config = $this->asyncConfig;

        if ($queue !== null) {
            $this->onQueue($queue);
        } elseif ($config?->queue !== null) {
            $this->onQueue($config->queue);
        }

        if ($delay !== null) {
            $this->delay($delay);
        } elseif ($config !== null && $config->delay > 0) {
            $this->delay($config->delay);
        }

        if ($tries !== null) {
            $this->tries = $tries;
        } elseif ($config !== null) {
            $this->tries = $config->retries;
        }

        if ($backoff !== null) {
            $this->backoff = $backoff;
        } elseif ($config !== null) {
            $this->backoff = $config->getBackoffArray();
        }

        if ($timeout !== null) {
            $this->timeout = $timeout;
        } elseif ($config?->timeout !== null) {
            $this->timeout = $config->timeout;
        }
    }

    /**
     * Load the Async attribute from the handler class.
     */
    protected function loadAsyncConfig(): ?Async
    {
        if (! class_exists($this->handlerClass)) {
            return null;
        }

        $reflection = new ReflectionClass($this->handlerClass);
        $attributes = $reflection->getAttributes(Async::class);

        if (empty($attributes)) {
            return null;
        }

        return $attributes[0]->newInstance();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $handler = app($this->handlerClass);

        if ($handler instanceof ExtensionHandlerContract) {
            $handler->handle($this->extensionPoint);
        } elseif (is_callable($handler)) {
            $handler($this->extensionPoint);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        $config = $this->asyncConfig;

        if ($config?->onFailure !== null && class_exists($config->onFailure)) {
            $failureHandler = app($config->onFailure);

            if (method_exists($failureHandler, 'handle')) {
                $failureHandler->handle($this->extensionPoint, $exception);
            } elseif (is_callable($failureHandler)) {
                $failureHandler($this->extensionPoint, $exception);
            }
        }

        if (config('extensions.debug', false)) {
            logger()->error('[Extensions] Async handler failed permanently', [
                'handler' => $this->handlerClass,
                'extension_point' => $this->extensionPoint::class,
                'error' => $exception?->getMessage(),
            ]);
        }
    }

    /**
     * Handle a job retry.
     */
    public function retrying(): void
    {
        $config = $this->asyncConfig;

        if ($config?->onRetry !== null && class_exists($config->onRetry)) {
            $retryHandler = app($config->onRetry);

            if (method_exists($retryHandler, 'handle')) {
                $retryHandler->handle($this->extensionPoint, $this->attempts());
            } elseif (is_callable($retryHandler)) {
                $retryHandler($this->extensionPoint, $this->attempts());
            }
        }

        if (config('extensions.debug', false)) {
            logger()->warning('[Extensions] Async handler retrying', [
                'handler' => $this->handlerClass,
                'extension_point' => $this->extensionPoint::class,
                'attempt' => $this->attempts(),
            ]);
        }
    }

    /**
     * Get the unique ID for the job (if unique).
     */
    public function uniqueId(): string
    {
        return $this->handlerClass . ':' . $this->extensionPoint::class;
    }

    /**
     * The number of seconds after which the job's unique lock will be released.
     */
    public function uniqueFor(): ?int
    {
        return $this->asyncConfig?->uniqueLockTimeout;
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<string>
     */
    public function tags(): array
    {
        return [
            'extension-handler',
            'handler:' . $this->handlerClass,
            'extension:' . $this->extensionPoint::class,
        ];
    }

    /**
     * Determine if the job should be unique.
     */
    public function shouldBeUnique(): bool
    {
        return $this->asyncConfig?->uniqueJob ?? false;
    }
}
