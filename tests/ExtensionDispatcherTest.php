<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Tests;

use Esegments\LaravelExtensions\ExtensionDispatcher;
use Esegments\LaravelExtensions\Facades\Extensions;
use Esegments\LaravelExtensions\HandlerRegistry;
use Esegments\LaravelExtensions\Tests\Fixtures\InterruptibleExtensionPoint;
use Esegments\LaravelExtensions\Tests\Fixtures\PipeableExtensionPoint;
use Esegments\LaravelExtensions\Tests\Fixtures\SimpleExtensionPoint;
use Esegments\LaravelExtensions\Tests\Fixtures\SimpleHandler;
use Esegments\LaravelExtensions\Tests\Fixtures\VetoHandler;
use Illuminate\Support\Facades\Event;

final class ExtensionDispatcherTest extends TestCase
{
    private ExtensionDispatcher $dispatcher;

    private HandlerRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = $this->app->make(HandlerRegistry::class);
        $this->dispatcher = $this->app->make(ExtensionDispatcher::class);
        $this->registry->clear();
    }

    public function test_dispatches_to_registered_handlers(): void
    {
        $this->registry->register(
            SimpleExtensionPoint::class,
            fn (SimpleExtensionPoint $ext) => $ext->processed[] = 'handler1',
        );

        $extensionPoint = new SimpleExtensionPoint();
        $result = $this->dispatcher->dispatch($extensionPoint);

        $this->assertSame($extensionPoint, $result);
        $this->assertEquals(['handler1'], $extensionPoint->processed);
    }

    public function test_dispatches_handlers_in_priority_order(): void
    {
        $this->registry->register(
            SimpleExtensionPoint::class,
            fn (SimpleExtensionPoint $ext) => $ext->processed[] = 'low',
            priority: 200,
        );
        $this->registry->register(
            SimpleExtensionPoint::class,
            fn (SimpleExtensionPoint $ext) => $ext->processed[] = 'high',
            priority: 10,
        );
        $this->registry->register(
            SimpleExtensionPoint::class,
            fn (SimpleExtensionPoint $ext) => $ext->processed[] = 'normal',
            priority: 100,
        );

        $extensionPoint = new SimpleExtensionPoint();
        $this->dispatcher->dispatch($extensionPoint);

        $this->assertEquals(['high', 'normal', 'low'], $extensionPoint->processed);
    }

    public function test_resolves_handler_classes_from_container(): void
    {
        $this->app->bind(SimpleHandler::class, fn () => new SimpleHandler('Injected'));
        $this->registry->register(SimpleExtensionPoint::class, SimpleHandler::class);

        $extensionPoint = new SimpleExtensionPoint();
        $this->dispatcher->dispatch($extensionPoint);

        $this->assertEquals(['Injected'], $extensionPoint->processed);
    }

    public function test_interrupts_on_false_return_for_interruptible(): void
    {
        $this->registry->register(
            InterruptibleExtensionPoint::class,
            fn (InterruptibleExtensionPoint $ext) => $ext->processed[] = 'first',
            priority: 10,
        );
        $this->registry->register(
            InterruptibleExtensionPoint::class,
            function (InterruptibleExtensionPoint $ext) {
                $ext->processed[] = 'veto';

                return false; // Interrupt
            },
            priority: 20,
        );
        $this->registry->register(
            InterruptibleExtensionPoint::class,
            fn (InterruptibleExtensionPoint $ext) => $ext->processed[] = 'never_called',
            priority: 30,
        );

        $extensionPoint = new InterruptibleExtensionPoint();
        $this->dispatcher->dispatch($extensionPoint);

        $this->assertTrue($extensionPoint->wasInterrupted());
        $this->assertEquals(['first', 'veto'], $extensionPoint->processed);
        $this->assertNotContains('never_called', $extensionPoint->processed);
    }

    public function test_dispatch_interruptible_returns_can_proceed(): void
    {
        // No handlers - should proceed
        $extensionPoint = new InterruptibleExtensionPoint();
        $canProceed = $this->dispatcher->dispatchInterruptible($extensionPoint);

        $this->assertTrue($canProceed);
        $this->assertFalse($extensionPoint->wasInterrupted());
    }

    public function test_dispatch_interruptible_returns_false_when_vetoed(): void
    {
        $this->registry->register(
            InterruptibleExtensionPoint::class,
            fn () => false,
        );

        $extensionPoint = new InterruptibleExtensionPoint();
        $canProceed = $this->dispatcher->dispatchInterruptible($extensionPoint);

        $this->assertFalse($canProceed);
        $this->assertTrue($extensionPoint->wasInterrupted());
    }

    public function test_sets_interrupted_by_handler_name(): void
    {
        $this->app->bind(VetoHandler::class, fn () => new VetoHandler(shouldVeto: true));
        $this->registry->register(InterruptibleExtensionPoint::class, VetoHandler::class);

        $extensionPoint = new InterruptibleExtensionPoint();
        $this->dispatcher->dispatch($extensionPoint);

        $this->assertTrue($extensionPoint->wasInterrupted());
        $this->assertEquals(VetoHandler::class, $extensionPoint->getInterruptedBy());
    }

    public function test_pipeable_extension_point_data_transformed(): void
    {
        $this->registry->register(
            PipeableExtensionPoint::class,
            function (PipeableExtensionPoint $ext) {
                $ext->price *= 0.9; // 10% discount
                $ext->transformations[] = 'discount';
            },
            priority: 10,
        );
        $this->registry->register(
            PipeableExtensionPoint::class,
            function (PipeableExtensionPoint $ext) {
                $ext->price *= 1.1; // 10% tax
                $ext->transformations[] = 'tax';
            },
            priority: 20,
        );

        $extensionPoint = new PipeableExtensionPoint(price: 100.0);
        $this->dispatcher->dispatch($extensionPoint);

        $this->assertEqualsWithDelta(99.0, $extensionPoint->price, 0.0001); // 100 * 0.9 * 1.1
        $this->assertEquals(['discount', 'tax'], $extensionPoint->transformations);
    }

    public function test_dispatches_as_laravel_event(): void
    {
        Event::fake();

        // Re-create dispatcher after Event::fake() so it uses the fake event dispatcher
        $dispatcher = new ExtensionDispatcher(
            container: $this->app,
            registry: $this->registry,
            events: $this->app->make(\Illuminate\Contracts\Events\Dispatcher::class),
            dispatchAsEvents: true,
        );

        $extensionPoint = new SimpleExtensionPoint();
        $dispatcher->dispatch($extensionPoint);

        Event::assertDispatched(SimpleExtensionPoint::class);
    }

    public function test_dispatch_silent_skips_laravel_events(): void
    {
        Event::fake();

        $extensionPoint = new SimpleExtensionPoint();
        $this->dispatcher->dispatchSilent($extensionPoint);

        Event::assertNotDispatched(SimpleExtensionPoint::class);
    }

    public function test_has_handlers_delegates_to_registry(): void
    {
        $this->assertFalse($this->dispatcher->hasHandlers(SimpleExtensionPoint::class));

        $this->registry->register(SimpleExtensionPoint::class, fn () => null);

        $this->assertTrue($this->dispatcher->hasHandlers(SimpleExtensionPoint::class));
    }

    public function test_facade_dispatch_works(): void
    {
        Extensions::register(
            SimpleExtensionPoint::class,
            fn (SimpleExtensionPoint $ext) => $ext->processed[] = 'via_facade',
        );

        $extensionPoint = new SimpleExtensionPoint();
        $result = Extensions::dispatch($extensionPoint);

        $this->assertSame($extensionPoint, $result);
        $this->assertEquals(['via_facade'], $extensionPoint->processed);
    }

    public function test_facade_registry_access(): void
    {
        Extensions::registry()->register(
            SimpleExtensionPoint::class,
            fn () => null,
        );

        $this->assertTrue(Extensions::hasHandlers(SimpleExtensionPoint::class));
    }

    public function test_handles_no_registered_handlers_gracefully(): void
    {
        $extensionPoint = new SimpleExtensionPoint();
        $result = $this->dispatcher->dispatch($extensionPoint);

        $this->assertSame($extensionPoint, $result);
        $this->assertEmpty($extensionPoint->processed);
    }

    public function test_closure_handler_with_injected_dependencies(): void
    {
        $this->app->singleton('test.value', fn () => 'injected');

        $this->registry->register(
            SimpleExtensionPoint::class,
            function (SimpleExtensionPoint $ext) {
                $ext->processed[] = app('test.value');
            },
        );

        $extensionPoint = new SimpleExtensionPoint();
        $this->dispatcher->dispatch($extensionPoint);

        $this->assertEquals(['injected'], $extensionPoint->processed);
    }
}
