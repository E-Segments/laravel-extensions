<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Tests;

use Esegments\LaravelExtensions\Exceptions\ExtensionException;
use Esegments\LaravelExtensions\HandlerRegistry;
use Esegments\LaravelExtensions\Tests\Fixtures\CountingHandler;
use Esegments\LaravelExtensions\Tests\Fixtures\InterruptibleExtension;
use Esegments\LaravelExtensions\Tests\Fixtures\InterruptingHandler;
use Esegments\LaravelExtensions\Tests\Fixtures\SimpleExtension;
use Esegments\LaravelExtensions\Tests\Fixtures\SimpleHandler;

final class HandlerRegistryTest extends TestCase
{
    private HandlerRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new HandlerRegistry;
    }

    public function test_can_register_class_handler(): void
    {
        $this->registry->register(
            SimpleExtension::class,
            SimpleHandler::class,
        );

        $this->assertTrue($this->registry->hasHandlers(SimpleExtension::class));
        $this->assertEquals(1, $this->registry->countHandlers(SimpleExtension::class));
    }

    public function test_can_register_closure_handler(): void
    {
        $this->registry->register(
            SimpleExtension::class,
            fn (SimpleExtension $ext) => $ext->addData('closure', 'value'),
        );

        $this->assertTrue($this->registry->hasHandlers(SimpleExtension::class));
    }

    public function test_handlers_are_sorted_by_priority(): void
    {
        $this->registry->register(SimpleExtension::class, 'HandlerC', priority: 150);
        $this->registry->register(SimpleExtension::class, 'HandlerA', priority: 50);
        $this->registry->register(SimpleExtension::class, 'HandlerB', priority: 100);

        $handlers = $this->registry->getHandlers(SimpleExtension::class);

        $this->assertEquals('HandlerA', $handlers[0]['handler']);
        $this->assertEquals('HandlerB', $handlers[1]['handler']);
        $this->assertEquals('HandlerC', $handlers[2]['handler']);
    }

    public function test_default_priority_is_100(): void
    {
        $this->registry->register(SimpleExtension::class, 'Handler1');
        $this->registry->register(SimpleExtension::class, 'Handler2', priority: 50);

        $handlers = $this->registry->getHandlers(SimpleExtension::class);

        $this->assertEquals(50, $handlers[0]['priority']);
        $this->assertEquals(100, $handlers[1]['priority']);
    }

    public function test_can_remove_handler(): void
    {
        $this->registry->register(SimpleExtension::class, SimpleHandler::class);
        $this->assertEquals(1, $this->registry->countHandlers(SimpleExtension::class));

        $this->registry->removeHandler(SimpleExtension::class, SimpleHandler::class);
        $this->assertEquals(0, $this->registry->countHandlers(SimpleExtension::class));
    }

    public function test_can_forget_all_handlers_for_extension(): void
    {
        $this->registry->register(SimpleExtension::class, 'Handler1');
        $this->registry->register(SimpleExtension::class, 'Handler2');

        $this->registry->forget(SimpleExtension::class);

        $this->assertFalse($this->registry->hasHandlers(SimpleExtension::class));
    }

    public function test_can_clear_all_handlers(): void
    {
        $this->registry->register(SimpleExtension::class, 'Handler1');
        $this->registry->register(InterruptibleExtension::class, 'Handler2');

        $this->registry->clear();

        $this->assertFalse($this->registry->hasHandlers(SimpleExtension::class));
        $this->assertFalse($this->registry->hasHandlers(InterruptibleExtension::class));
    }

    public function test_throws_exception_for_invalid_extension_point_class(): void
    {
        $this->expectException(ExtensionException::class);

        $this->registry->register(
            \stdClass::class, // Not an ExtensionPointContract
            SimpleHandler::class,
        );
    }

    public function test_returns_empty_array_for_unregistered_extension(): void
    {
        $handlers = $this->registry->getHandlers(SimpleExtension::class);

        $this->assertEquals([], $handlers);
    }

    public function test_can_get_registered_extension_points(): void
    {
        $this->registry->register(SimpleExtension::class, 'Handler1');
        $this->registry->register(InterruptibleExtension::class, 'Handler2');

        $extensionPoints = $this->registry->getRegisteredExtensionPoints();

        $this->assertContains(SimpleExtension::class, $extensionPoints);
        $this->assertContains(InterruptibleExtension::class, $extensionPoints);
    }

    public function test_sorted_cache_is_invalidated_on_register(): void
    {
        $this->registry->register(SimpleExtension::class, 'HandlerB', priority: 100);

        // Force cache population
        $this->registry->getHandlers(SimpleExtension::class);

        // Register new handler with higher priority
        $this->registry->register(SimpleExtension::class, 'HandlerA', priority: 50);

        $handlers = $this->registry->getHandlers(SimpleExtension::class);

        $this->assertEquals('HandlerA', $handlers[0]['handler']);
    }

    public function test_can_register_handler_group(): void
    {
        $this->registry->registerGroup('security', [
            [InterruptibleExtension::class, InterruptingHandler::class, 10],
            [InterruptibleExtension::class, CountingHandler::class, 20],
        ]);

        $this->assertEquals(2, $this->registry->countHandlers(InterruptibleExtension::class));
        $this->assertContains('security', $this->registry->getRegisteredGroups());
    }

    public function test_can_disable_handler_group(): void
    {
        $this->registry->registerGroup('security', [
            [InterruptibleExtension::class, InterruptingHandler::class, 10],
        ]);

        $this->assertEquals(1, $this->registry->countHandlers(InterruptibleExtension::class));

        $this->registry->disableGroup('security');

        $this->assertTrue($this->registry->isGroupDisabled('security'));
        $this->assertEquals(0, $this->registry->countHandlers(InterruptibleExtension::class));
    }

    public function test_can_enable_handler_group(): void
    {
        $this->registry->registerGroup('security', [
            [InterruptibleExtension::class, InterruptingHandler::class, 10],
        ]);

        $this->registry->disableGroup('security');
        $this->assertEquals(0, $this->registry->countHandlers(InterruptibleExtension::class));

        $this->registry->enableGroup('security');
        $this->assertFalse($this->registry->isGroupDisabled('security'));
        $this->assertEquals(1, $this->registry->countHandlers(InterruptibleExtension::class));
    }

    public function test_fluent_interface(): void
    {
        $result = $this->registry
            ->register(SimpleExtension::class, 'Handler1')
            ->register(InterruptibleExtension::class, 'Handler2')
            ->forget(SimpleExtension::class)
            ->clear();

        $this->assertInstanceOf(HandlerRegistry::class, $result);
    }
}
