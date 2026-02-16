<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Tests;

use Esegments\LaravelExtensions\ExtensionDispatcher;
use Esegments\LaravelExtensions\HandlerRegistry;
use Esegments\LaravelExtensions\Jobs\DispatchAsyncHandler;
use Esegments\LaravelExtensions\Tests\Fixtures\AsyncHandler;
use Esegments\LaravelExtensions\Tests\Fixtures\SimpleExtension;
use Esegments\LaravelExtensions\Tests\Fixtures\SimpleHandler;
use Illuminate\Support\Facades\Queue;

final class AsyncHandlerTest extends TestCase
{
    private HandlerRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = $this->app->make(HandlerRegistry::class);
    }

    public function test_async_handler_is_dispatched_to_queue(): void
    {
        Queue::fake();

        $dispatcher = new ExtensionDispatcher(
            container: $this->app,
            registry: $this->registry,
            events: null,
        );

        $this->registry->register(SimpleExtension::class, AsyncHandler::class);

        $extension = new SimpleExtension;
        $dispatcher->dispatch($extension);

        Queue::assertPushed(DispatchAsyncHandler::class, function ($job) {
            return $job->handlerClass === AsyncHandler::class;
        });

        // The extension should NOT have the data yet (handler runs async)
        $this->assertArrayNotHasKey('async_handler', $extension->data);
    }

    public function test_sync_handler_is_not_dispatched_to_queue(): void
    {
        Queue::fake();

        $dispatcher = new ExtensionDispatcher(
            container: $this->app,
            registry: $this->registry,
            events: null,
        );

        $this->registry->register(SimpleExtension::class, SimpleHandler::class);

        $extension = new SimpleExtension;
        $dispatcher->dispatch($extension);

        Queue::assertNothingPushed();

        // The extension SHOULD have the data (handler runs sync)
        $this->assertEquals('executed', $extension->data['simple_handler']);
    }

    public function test_mixed_sync_and_async_handlers(): void
    {
        Queue::fake();

        $dispatcher = new ExtensionDispatcher(
            container: $this->app,
            registry: $this->registry,
            events: null,
        );

        $this->registry->register(SimpleExtension::class, SimpleHandler::class, priority: 10);
        $this->registry->register(SimpleExtension::class, AsyncHandler::class, priority: 20);

        $extension = new SimpleExtension;
        $dispatcher->dispatch($extension);

        // Sync handler should have executed
        $this->assertEquals('executed', $extension->data['simple_handler']);

        // Async handler should be queued
        Queue::assertPushed(DispatchAsyncHandler::class);
    }

    public function test_dispatch_async_handler_job_executes_handler(): void
    {
        $extension = new SimpleExtension;
        $job = new DispatchAsyncHandler(
            SimpleHandler::class,
            $extension
        );

        $job->handle();

        $this->assertEquals('executed', $extension->data['simple_handler']);
    }

    public function test_dispatch_async_handler_job_has_correct_tags(): void
    {
        $extension = new SimpleExtension;
        $job = new DispatchAsyncHandler(
            SimpleHandler::class,
            $extension
        );

        $tags = $job->tags();

        $this->assertContains('extension-handler', $tags);
        $this->assertContains('handler:'.SimpleHandler::class, $tags);
        $this->assertContains('extension:'.SimpleExtension::class, $tags);
    }

    public function test_dispatch_async_handler_can_specify_queue(): void
    {
        Queue::fake();

        $extension = new SimpleExtension;

        DispatchAsyncHandler::dispatch(
            SimpleHandler::class,
            $extension,
            'custom-queue'
        );

        Queue::assertPushedOn('custom-queue', DispatchAsyncHandler::class);
    }
}
