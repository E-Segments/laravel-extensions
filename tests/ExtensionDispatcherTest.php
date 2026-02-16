<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Tests;

use Esegments\LaravelExtensions\ExtensionDispatcher;
use Esegments\LaravelExtensions\Facades\Extensions;
use Esegments\LaravelExtensions\HandlerRegistry;
use Esegments\LaravelExtensions\Tests\Fixtures\CountingHandler;
use Esegments\LaravelExtensions\Tests\Fixtures\InterruptibleExtension;
use Esegments\LaravelExtensions\Tests\Fixtures\InterruptingHandler;
use Esegments\LaravelExtensions\Tests\Fixtures\SimpleExtension;
use Esegments\LaravelExtensions\Tests\Fixtures\SimpleHandler;
use Illuminate\Support\Facades\Event;

final class ExtensionDispatcherTest extends TestCase
{
    private HandlerRegistry $registry;

    private ExtensionDispatcher $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = $this->app->make(HandlerRegistry::class);
        $this->dispatcher = $this->app->make(ExtensionDispatcher::class);
    }

    public function test_can_dispatch_simple_extension(): void
    {
        $this->registry->register(SimpleExtension::class, SimpleHandler::class);

        $extension = new SimpleExtension('test');
        $result = $this->dispatcher->dispatch($extension);

        $this->assertSame($extension, $result);
        $this->assertEquals('executed', $extension->data['simple_handler']);
    }

    public function test_can_dispatch_with_closure_handler(): void
    {
        $this->registry->register(
            SimpleExtension::class,
            fn (SimpleExtension $ext) => $ext->addData('closure', 'executed'),
        );

        $extension = new SimpleExtension('test');
        $this->dispatcher->dispatch($extension);

        $this->assertEquals('executed', $extension->data['closure']);
    }

    public function test_handlers_are_executed_in_priority_order(): void
    {
        $order = [];

        $this->registry->register(
            SimpleExtension::class,
            function (SimpleExtension $ext) use (&$order) {
                $order[] = 'third';
            },
            priority: 150,
        );

        $this->registry->register(
            SimpleExtension::class,
            function (SimpleExtension $ext) use (&$order) {
                $order[] = 'first';
            },
            priority: 50,
        );

        $this->registry->register(
            SimpleExtension::class,
            function (SimpleExtension $ext) use (&$order) {
                $order[] = 'second';
            },
            priority: 100,
        );

        $this->dispatcher->dispatch(new SimpleExtension('test'));

        $this->assertEquals(['first', 'second', 'third'], $order);
    }

    public function test_interruptible_extension_can_be_interrupted(): void
    {
        $this->registry->register(
            InterruptibleExtension::class,
            InterruptingHandler::class,
            priority: 10,
        );

        $extension = new InterruptibleExtension(total: 1000.00);
        $canProceed = $this->dispatcher->dispatchInterruptible($extension);

        $this->assertFalse($canProceed);
        $this->assertTrue($extension->wasInterrupted());
        $this->assertEquals(InterruptingHandler::class, $extension->getInterruptedBy());
        $this->assertNotEmpty($extension->errors);
    }

    public function test_handlers_after_interruption_are_not_executed(): void
    {
        $this->registry->register(
            InterruptibleExtension::class,
            InterruptingHandler::class,
            priority: 10,
        );

        $this->registry->register(
            InterruptibleExtension::class,
            CountingHandler::class,
            priority: 20,
        );

        $extension = new InterruptibleExtension(total: 1000.00);
        $this->dispatcher->dispatchInterruptible($extension);

        // Only the interrupting handler should have run
        $this->assertEquals(1, $extension->processedCount);
    }

    public function test_interruptible_extension_completes_when_not_interrupted(): void
    {
        $this->registry->register(
            InterruptibleExtension::class,
            CountingHandler::class,
            priority: 10,
        );

        $this->registry->register(
            InterruptibleExtension::class,
            CountingHandler::class,
            priority: 20,
        );

        $extension = new InterruptibleExtension(total: 100.00);
        $canProceed = $this->dispatcher->dispatchInterruptible($extension);

        $this->assertTrue($canProceed);
        $this->assertFalse($extension->wasInterrupted());
        $this->assertEquals(2, $extension->processedCount);
    }

    public function test_dispatch_with_results_collects_handler_results(): void
    {
        // Use class-based handlers to get unique keys in results
        $this->registry->register(
            SimpleExtension::class,
            SimpleHandler::class,
            priority: 10,
        );

        $this->registry->register(
            SimpleExtension::class,
            CountingHandler::class,
            priority: 20,
        );

        $extension = new SimpleExtension('test');
        $result = $this->dispatcher->dispatchWithResults($extension);

        $this->assertInstanceOf(\Esegments\LaravelExtensions\Results\DispatchResult::class, $result);
        $this->assertSame($extension, $result->extension());
        $this->assertCount(2, $result->successful());
        $this->assertTrue($result->successful()->contains(SimpleHandler::class));
        $this->assertTrue($result->successful()->contains(CountingHandler::class));
    }

    public function test_has_handlers_returns_correct_value(): void
    {
        $this->assertFalse($this->dispatcher->hasHandlers(SimpleExtension::class));

        $this->registry->register(SimpleExtension::class, SimpleHandler::class);

        $this->assertTrue($this->dispatcher->hasHandlers(SimpleExtension::class));
    }

    public function test_facade_works(): void
    {
        $this->registry->register(SimpleExtension::class, SimpleHandler::class);

        $extension = new SimpleExtension('test');
        $result = Extensions::dispatch($extension);

        $this->assertSame($extension, $result);
        $this->assertEquals('executed', $extension->data['simple_handler']);
    }

    public function test_extension_is_dispatched_as_laravel_event(): void
    {
        Event::fake();

        // Rebuild dispatcher with the faked event dispatcher
        $dispatcher = new ExtensionDispatcher(
            container: $this->app,
            registry: $this->registry,
            events: $this->app->make('events'),
        );

        $extension = new SimpleExtension('test');
        $dispatcher->dispatch($extension);

        Event::assertDispatched(SimpleExtension::class);
    }

    public function test_interruptible_extension_is_dispatched_as_laravel_event(): void
    {
        Event::fake();

        // Rebuild dispatcher with the faked event dispatcher
        $dispatcher = new ExtensionDispatcher(
            container: $this->app,
            registry: $this->registry,
            events: $this->app->make('events'),
        );

        $extension = new InterruptibleExtension;
        $dispatcher->dispatchInterruptible($extension);

        Event::assertDispatched(InterruptibleExtension::class);
    }

    public function test_dispatch_with_no_handlers(): void
    {
        $extension = new SimpleExtension('test');
        $result = $this->dispatcher->dispatch($extension);

        $this->assertSame($extension, $result);
        $this->assertEmpty($extension->data);
    }

    public function test_invokable_handler_class(): void
    {
        $this->app->bind('InvokableHandler', function () {
            return new class
            {
                public function __invoke(SimpleExtension $ext): void
                {
                    $ext->addData('invokable', 'executed');
                }
            };
        });

        $this->registry->register(SimpleExtension::class, 'InvokableHandler');

        $extension = new SimpleExtension('test');
        $this->dispatcher->dispatch($extension);

        $this->assertEquals('executed', $extension->data['invokable']);
    }

    public function test_dispatch_silent_does_not_fire_laravel_event(): void
    {
        Event::fake();

        $this->registry->register(SimpleExtension::class, SimpleHandler::class);

        $extension = new SimpleExtension('test');
        $result = $this->dispatcher->dispatchSilent($extension);

        $this->assertSame($extension, $result);
        $this->assertEquals('executed', $extension->data['simple_handler']);
        Event::assertNotDispatched(SimpleExtension::class);
    }

    public function test_dispatch_interruptible_silent_does_not_fire_laravel_event(): void
    {
        Event::fake();

        $this->registry->register(InterruptibleExtension::class, CountingHandler::class);

        $extension = new InterruptibleExtension;
        $canProceed = $this->dispatcher->dispatchInterruptibleSilent($extension);

        $this->assertTrue($canProceed);
        $this->assertEquals(1, $extension->processedCount);
        Event::assertNotDispatched(InterruptibleExtension::class);
    }

    public function test_dispatch_interruptible_silent_handles_interruption(): void
    {
        Event::fake();

        $this->registry->register(
            InterruptibleExtension::class,
            InterruptingHandler::class,
            priority: 10,
        );

        $extension = new InterruptibleExtension(total: 1000.00);
        $canProceed = $this->dispatcher->dispatchInterruptibleSilent($extension);

        $this->assertFalse($canProceed);
        $this->assertTrue($extension->wasInterrupted());
        Event::assertNotDispatched(InterruptibleExtension::class);
    }

    public function test_facade_dispatch_silent_works(): void
    {
        Event::fake();

        $this->registry->register(SimpleExtension::class, SimpleHandler::class);

        $extension = new SimpleExtension('test');
        $result = Extensions::dispatchSilent($extension);

        $this->assertSame($extension, $result);
        Event::assertNotDispatched(SimpleExtension::class);
    }
}
