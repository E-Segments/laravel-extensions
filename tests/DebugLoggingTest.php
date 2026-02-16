<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Tests;

use Esegments\LaravelExtensions\ExtensionDispatcher;
use Esegments\LaravelExtensions\HandlerRegistry;
use Esegments\LaravelExtensions\Support\DebugInfo;
use Esegments\LaravelExtensions\Tests\Fixtures\InterruptibleExtension;
use Esegments\LaravelExtensions\Tests\Fixtures\SimpleExtension;
use Esegments\LaravelExtensions\Tests\Fixtures\SimpleHandler;
use Illuminate\Support\Facades\Log;

final class DebugLoggingTest extends TestCase
{
    private HandlerRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = $this->app->make(HandlerRegistry::class);
    }

    public function test_debug_info_records_handler_executions(): void
    {
        $debugInfo = new DebugInfo(SimpleExtension::class, microtime(true));

        $debugInfo->recordHandler('Handler1', 1.5, 'result1');
        $debugInfo->recordHandler('Handler2', 2.3, null, 'error message');

        $this->assertCount(2, $debugInfo->handlerExecutions);
        $this->assertEquals('Handler1', $debugInfo->handlerExecutions[0]['handler']);
        $this->assertEquals(1.5, $debugInfo->handlerExecutions[0]['duration_ms']);
        $this->assertEquals('result1', $debugInfo->handlerExecutions[0]['result']);
        $this->assertNull($debugInfo->handlerExecutions[0]['error']);

        $this->assertEquals('error message', $debugInfo->handlerExecutions[1]['error']);
    }

    public function test_debug_info_marks_interrupted(): void
    {
        $extension = new InterruptibleExtension;
        $extension->interrupt();
        $extension->setInterruptedBy('SomeHandler');

        $debugInfo = new DebugInfo(InterruptibleExtension::class, microtime(true));
        $debugInfo->complete($extension);

        $this->assertTrue($debugInfo->wasInterrupted);
        $this->assertEquals('SomeHandler', $debugInfo->interruptedBy);
    }

    public function test_debug_info_to_array(): void
    {
        $debugInfo = new DebugInfo(SimpleExtension::class, microtime(true));
        $debugInfo->recordHandler('Handler1', 1.0, null);

        $extension = new SimpleExtension;
        $debugInfo->complete($extension);

        $array = $debugInfo->toArray();

        $this->assertArrayHasKey('extension_point', $array);
        $this->assertArrayHasKey('total_duration_ms', $array);
        $this->assertArrayHasKey('handler_count', $array);
        $this->assertArrayHasKey('was_interrupted', $array);
        $this->assertArrayHasKey('handlers', $array);

        $this->assertEquals(SimpleExtension::class, $array['extension_point']);
        $this->assertEquals(1, $array['handler_count']);
        $this->assertFalse($array['was_interrupted']);
    }

    public function test_dispatcher_with_debug_enabled_logs(): void
    {
        Log::shouldReceive('getFacadeRoot')->andReturnSelf();
        Log::shouldReceive('debug')->once();

        $dispatcher = new ExtensionDispatcher(
            container: $this->app,
            registry: $this->registry,
            events: null,
            debug: true,
        );

        $this->registry->register(SimpleExtension::class, SimpleHandler::class);

        $dispatcher->dispatch(new SimpleExtension);
    }

    public function test_dispatcher_is_debug_enabled_returns_correct_value(): void
    {
        $dispatcherWithDebug = new ExtensionDispatcher(
            container: $this->app,
            registry: $this->registry,
            events: null,
            debug: true,
        );

        $dispatcherWithoutDebug = new ExtensionDispatcher(
            container: $this->app,
            registry: $this->registry,
            events: null,
            debug: false,
        );

        $this->assertTrue($dispatcherWithDebug->isDebugEnabled());
        $this->assertFalse($dispatcherWithoutDebug->isDebugEnabled());
    }

    public function test_dispatch_with_results_includes_debug_info_when_enabled(): void
    {
        $dispatcher = new ExtensionDispatcher(
            container: $this->app,
            registry: $this->registry,
            events: null,
            debug: true,
        );

        $this->registry->register(SimpleExtension::class, SimpleHandler::class);

        $result = $dispatcher->dispatchWithResults(new SimpleExtension);

        $this->assertInstanceOf(\Esegments\LaravelExtensions\Results\DispatchResult::class, $result);
        $this->assertInstanceOf(DebugInfo::class, $result->debug());
    }

    public function test_dispatch_with_results_has_null_debug_when_disabled(): void
    {
        $dispatcher = new ExtensionDispatcher(
            container: $this->app,
            registry: $this->registry,
            events: null,
            debug: false,
        );

        $this->registry->register(SimpleExtension::class, SimpleHandler::class);

        $result = $dispatcher->dispatchWithResults(new SimpleExtension);

        $this->assertInstanceOf(\Esegments\LaravelExtensions\Results\DispatchResult::class, $result);
        $this->assertNull($result->debug());
    }
}
