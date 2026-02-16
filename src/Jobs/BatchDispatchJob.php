<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Jobs;

use Esegments\LaravelExtensions\Contracts\ExtensionPointContract;
use Esegments\LaravelExtensions\ExtensionDispatcher;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;

/**
 * Job for batch dispatching multiple extension points.
 *
 * @example
 * ```php
 * Extensions::batch([
 *     new OrderPlaced($order1),
 *     new OrderPlaced($order2),
 *     new OrderPlaced($order3),
 * ])->dispatch();
 * ```
 */
final class BatchDispatchJob implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    public function __construct(
        public readonly ExtensionPointContract $extensionPoint,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(ExtensionDispatcher $dispatcher): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $dispatcher->dispatch($this->extensionPoint);
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<string>
     */
    public function tags(): array
    {
        return [
            'extension-batch',
            'extension:' . $this->extensionPoint::class,
        ];
    }

    /**
     * Create a batch from multiple extension points.
     *
     * @param  array<ExtensionPointContract>  $extensionPoints
     * @return \Illuminate\Bus\PendingBatch
     */
    public static function createBatch(array $extensionPoints): \Illuminate\Bus\PendingBatch
    {
        $jobs = array_map(
            fn (ExtensionPointContract $point) => new static($point),
            $extensionPoints,
        );

        return Bus::batch($jobs)->name('extension-batch-dispatch');
    }
}
