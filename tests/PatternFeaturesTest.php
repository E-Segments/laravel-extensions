<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Tests;

use Closure;
use Esegments\LaravelExtensions\Contracts\ExtensionPointContract;
use Esegments\LaravelExtensions\Contracts\PipelineContract;
use Esegments\LaravelExtensions\ExtensionDispatcher;
use Esegments\LaravelExtensions\Jobs\BatchDispatchJob;
use Esegments\LaravelExtensions\Pipeline\ExtensionPipeline;
use Esegments\LaravelExtensions\Strategies\FirstResultStrategy;
use Esegments\LaravelExtensions\Strategies\MergeResultsStrategy;
use Esegments\LaravelExtensions\Strategies\ReduceResultsStrategy;
use Esegments\LaravelExtensions\Tests\Fixtures\SimpleExtensionPoint;
use Illuminate\Support\Collection;
use RuntimeException;

class PatternFeaturesTest extends TestCase
{
    // =========================================
    // FirstResultStrategy Tests
    // =========================================

    public function test_first_result_strategy_returns_first_non_null(): void
    {
        $strategy = new FirstResultStrategy;

        $result = $strategy->aggregate([null, null, 'first', 'second']);

        $this->assertEquals('first', $result);
    }

    public function test_first_result_strategy_returns_null_when_all_null(): void
    {
        $strategy = new FirstResultStrategy;

        $result = $strategy->aggregate([null, null, null]);

        $this->assertNull($result);
    }

    public function test_first_result_strategy_should_stop_on_first_result(): void
    {
        $strategy = new FirstResultStrategy;

        $this->assertFalse($strategy->shouldStop(null));
        $this->assertTrue($strategy->shouldStop('result'));
        // Second call should not stop again
        $this->assertFalse($strategy->shouldStop('another'));
    }

    public function test_first_result_strategy_stores_first_result(): void
    {
        $strategy = new FirstResultStrategy;

        $strategy->shouldStop(null);
        $this->assertNull($strategy->getFirstResult());

        $strategy->shouldStop('found');
        $this->assertEquals('found', $strategy->getFirstResult());
    }

    public function test_first_result_strategy_can_reset(): void
    {
        $strategy = new FirstResultStrategy;

        $strategy->shouldStop('found');
        $this->assertEquals('found', $strategy->getFirstResult());

        $strategy->reset();

        $this->assertNull($strategy->getFirstResult());
        $this->assertTrue($strategy->shouldStop('new'));
    }

    public function test_first_result_strategy_handles_empty_array(): void
    {
        $strategy = new FirstResultStrategy;

        $result = $strategy->aggregate([]);

        $this->assertNull($result);
    }

    // =========================================
    // MergeResultsStrategy Tests
    // =========================================

    public function test_merge_strategy_returns_collection_by_default(): void
    {
        $strategy = new MergeResultsStrategy;

        $result = $strategy->aggregate(['a', 'b', 'c']);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(3, $result);
    }

    public function test_merge_strategy_can_return_array(): void
    {
        $strategy = new MergeResultsStrategy(asCollection: false);

        $result = $strategy->aggregate(['a', 'b', 'c']);

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
    }

    public function test_merge_strategy_as_array_fluent(): void
    {
        $strategy = (new MergeResultsStrategy)->asArray();

        $result = $strategy->aggregate(['a', 'b']);

        $this->assertIsArray($result);
    }

    public function test_merge_strategy_flattens_arrays_by_default(): void
    {
        $strategy = new MergeResultsStrategy;

        $result = $strategy->aggregate([['a', 'b'], ['c', 'd']]);

        $this->assertCount(4, $result);
        $this->assertEquals(['a', 'b', 'c', 'd'], $result->all());
    }

    public function test_merge_strategy_flattens_collections(): void
    {
        $strategy = new MergeResultsStrategy;

        $result = $strategy->aggregate([collect(['a', 'b']), collect(['c'])]);

        $this->assertCount(3, $result);
    }

    public function test_merge_strategy_can_preserve_nesting(): void
    {
        $strategy = (new MergeResultsStrategy)->preserveNesting();

        $result = $strategy->aggregate([['a', 'b'], ['c', 'd']]);

        $this->assertCount(2, $result);
        $this->assertEquals(['a', 'b'], $result->first());
    }

    public function test_merge_strategy_skips_null_values(): void
    {
        $strategy = new MergeResultsStrategy;

        $result = $strategy->aggregate(['a', null, 'b', null]);

        $this->assertCount(2, $result);
    }

    public function test_merge_strategy_never_stops(): void
    {
        $strategy = new MergeResultsStrategy;

        $this->assertFalse($strategy->shouldStop('anything'));
        $this->assertFalse($strategy->shouldStop(null));
    }

    // =========================================
    // ReduceResultsStrategy Tests
    // =========================================

    public function test_reduce_strategy_basic_reducer(): void
    {
        $strategy = new ReduceResultsStrategy(
            fn ($carry, $result) => $carry + $result,
            0,
        );

        $result = $strategy->aggregate([1, 2, 3, 4]);

        $this->assertEquals(10, $result);
    }

    public function test_reduce_strategy_sum_factory(): void
    {
        $strategy = ReduceResultsStrategy::sum();

        $result = $strategy->aggregate([10, 20, 30]);

        $this->assertEquals(60, $result);
    }

    public function test_reduce_strategy_sum_with_initial(): void
    {
        $strategy = ReduceResultsStrategy::sum(100);

        $result = $strategy->aggregate([5, 10]);

        $this->assertEquals(115, $result);
    }

    public function test_reduce_strategy_sum_handles_non_numeric(): void
    {
        $strategy = ReduceResultsStrategy::sum();

        $result = $strategy->aggregate([10, 'invalid', 20]);

        $this->assertEquals(30, $result);
    }

    public function test_reduce_strategy_concat_factory(): void
    {
        $strategy = ReduceResultsStrategy::concat();

        $result = $strategy->aggregate(['a', 'b', 'c']);

        $this->assertEquals('abc', $result);
    }

    public function test_reduce_strategy_concat_with_separator(): void
    {
        $strategy = ReduceResultsStrategy::concat(', ');

        $result = $strategy->aggregate(['apple', 'banana', 'cherry']);

        $this->assertEquals(', apple, banana, cherry', $result);
    }

    public function test_reduce_strategy_all_true(): void
    {
        $allTrueStrategy = ReduceResultsStrategy::allTrue();

        $this->assertTrue($allTrueStrategy->aggregate([true, true, true]));
        $this->assertFalse($allTrueStrategy->aggregate([true, false, true]));
    }

    public function test_reduce_strategy_any_true(): void
    {
        $anyTrueStrategy = ReduceResultsStrategy::anyTrue();

        $this->assertTrue($anyTrueStrategy->aggregate([false, true, false]));
        $this->assertFalse($anyTrueStrategy->aggregate([false, false, false]));
    }

    public function test_reduce_strategy_count(): void
    {
        $strategy = ReduceResultsStrategy::count();

        $result = $strategy->aggregate(['a', 'b', 'c', 'd', 'e']);

        $this->assertEquals(5, $result);
    }

    public function test_reduce_strategy_min(): void
    {
        $strategy = ReduceResultsStrategy::min();

        $result = $strategy->aggregate([5, 2, 8, 1, 9]);

        $this->assertEquals(1, $result);
    }

    public function test_reduce_strategy_max(): void
    {
        $strategy = ReduceResultsStrategy::max();

        $result = $strategy->aggregate([5, 2, 8, 1, 9]);

        $this->assertEquals(9, $result);
    }

    public function test_reduce_strategy_min_with_empty(): void
    {
        $strategy = ReduceResultsStrategy::min();

        $result = $strategy->aggregate([]);

        $this->assertNull($result);
    }

    public function test_reduce_strategy_skips_null_values(): void
    {
        $strategy = ReduceResultsStrategy::sum();

        $result = $strategy->aggregate([10, null, 20, null, 30]);

        $this->assertEquals(60, $result);
    }

    public function test_reduce_strategy_never_stops(): void
    {
        $strategy = ReduceResultsStrategy::count();

        $this->assertFalse($strategy->shouldStop('anything'));
        $this->assertFalse($strategy->shouldStop(null));
    }

    // =========================================
    // ExtensionPipeline Tests
    // =========================================

    public function test_pipeline_can_be_created_for_extension_point(): void
    {
        $extensionPoint = new SimpleExtensionPoint;

        $pipeline = ExtensionPipeline::for($extensionPoint);

        $this->assertInstanceOf(ExtensionPipeline::class, $pipeline);
    }

    public function test_pipeline_through_sets_pipes(): void
    {
        $extensionPoint = new SimpleExtensionPoint;

        $pipeline = (new ExtensionPipeline($extensionPoint))
            ->through([
                fn ($data, $next) => $next($data . 'a'),
                fn ($data, $next) => $next($data . 'b'),
            ]);

        $this->assertInstanceOf(ExtensionPipeline::class, $pipeline);
    }

    public function test_pipeline_pipe_adds_single_pipe(): void
    {
        $extensionPoint = new SimpleExtensionPoint;

        $pipeline = (new ExtensionPipeline($extensionPoint))
            ->pipe(fn ($data, $next) => $next($data))
            ->pipe(fn ($data, $next) => $next($data));

        $this->assertInstanceOf(ExtensionPipeline::class, $pipeline);
    }

    public function test_pipeline_runs_callable_pipes(): void
    {
        $extensionPoint = $this->createPipelineExtensionPoint('start');

        $pipeline = (new ExtensionPipeline($extensionPoint))
            ->through([
                fn ($data, $ext) => $data . '-first',
                fn ($data, $ext) => $data . '-second',
            ]);

        $result = $pipeline->run();

        $this->assertEquals('start-first-second', $result);
    }

    public function test_pipeline_calls_on_success_callback(): void
    {
        $extensionPoint = $this->createPipelineExtensionPoint('data');
        $successCalled = false;
        $successResult = null;

        $pipeline = (new ExtensionPipeline($extensionPoint))
            ->through([fn ($data, $ext) => $data . '-processed'])
            ->onSuccess(function ($result) use (&$successCalled, &$successResult) {
                $successCalled = true;
                $successResult = $result;
            });

        $pipeline->run();

        $this->assertTrue($successCalled);
        $this->assertEquals('data-processed', $successResult);
    }

    public function test_pipeline_calls_on_failure_callback(): void
    {
        $extensionPoint = $this->createPipelineExtensionPoint('data');
        $failureCalled = false;
        $caughtException = null;

        $pipeline = (new ExtensionPipeline($extensionPoint))
            ->through([
                function ($data, $ext) {
                    throw new RuntimeException('Pipeline error');
                },
            ])
            ->onFailure(function ($e) use (&$failureCalled, &$caughtException) {
                $failureCalled = true;
                $caughtException = $e;

                return 'fallback';
            });

        $result = $pipeline->run();

        $this->assertTrue($failureCalled);
        $this->assertInstanceOf(RuntimeException::class, $caughtException);
        $this->assertEquals('fallback', $result);
    }

    public function test_pipeline_throws_without_failure_handler(): void
    {
        $extensionPoint = $this->createPipelineExtensionPoint('data');

        $pipeline = (new ExtensionPipeline($extensionPoint))
            ->through([
                function ($data, $ext) {
                    throw new RuntimeException('Pipeline error');
                },
            ]);

        $this->expectException(RuntimeException::class);
        $pipeline->run();
    }

    public function test_pipeline_continue_on_failure(): void
    {
        $extensionPoint = $this->createPipelineExtensionPoint('start');

        $pipeline = (new ExtensionPipeline($extensionPoint))
            ->through([
                function ($data, $ext) {
                    throw new RuntimeException('First fails');
                },
                fn ($data, $ext) => $data . '-recovered',
            ])
            ->continueOnFailure();

        $result = $pipeline->run();

        $this->assertEquals('start-recovered', $result);
    }

    public function test_pipeline_updates_pipeline_contract_data(): void
    {
        $extensionPoint = $this->createPipelineExtensionPoint('initial');

        $pipeline = (new ExtensionPipeline($extensionPoint))
            ->through([
                fn ($data, $ext) => $data . '-transformed',
            ]);

        $pipeline->run();

        // The setPipelineData should have been called
        $this->assertEquals('initial-transformed', $extensionPoint->getPipelineData());
    }

    // =========================================
    // BatchDispatchJob Tests
    // =========================================

    public function test_batch_job_can_be_constructed(): void
    {
        $extensionPoint = new SimpleExtensionPoint;

        $job = new BatchDispatchJob($extensionPoint);

        $this->assertSame($extensionPoint, $job->extensionPoint);
    }

    public function test_batch_job_has_tags(): void
    {
        $extensionPoint = new SimpleExtensionPoint;
        $job = new BatchDispatchJob($extensionPoint);

        $tags = $job->tags();

        $this->assertContains('extension-batch', $tags);
        $this->assertContains('extension:' . SimpleExtensionPoint::class, $tags);
    }

    public function test_batch_job_has_tries_configured(): void
    {
        $job = new BatchDispatchJob(new SimpleExtensionPoint);

        $this->assertEquals(3, $job->tries);
    }

    public function test_batch_job_create_batch(): void
    {
        $points = [
            new SimpleExtensionPoint,
            new SimpleExtensionPoint,
            new SimpleExtensionPoint,
        ];

        $batch = BatchDispatchJob::createBatch($points);

        $this->assertInstanceOf(\Illuminate\Bus\PendingBatch::class, $batch);
    }

    public function test_batch_job_handles_dispatch(): void
    {
        $extensionPoint = new SimpleExtensionPoint;
        $job = new BatchDispatchJob($extensionPoint);

        // Use real dispatcher since ExtensionDispatcher is final
        $dispatcher = $this->app->make(ExtensionDispatcher::class);

        // Just ensure it runs without error
        $job->handle($dispatcher);

        $this->assertTrue(true);
    }

    // =========================================
    // Helper Methods
    // =========================================

    /**
     * Create a mock pipeline extension point.
     */
    protected function createPipelineExtensionPoint(string $initialData): PipelineContract
    {
        return new class($initialData) implements PipelineContract
        {
            private mixed $data;

            public function __construct(string $data)
            {
                $this->data = $data;
            }

            public function getPipelineData(): mixed
            {
                return $this->data;
            }

            public function setPipelineData(mixed $data): void
            {
                $this->data = $data;
            }
        };
    }
}
