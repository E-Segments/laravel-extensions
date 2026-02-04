<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Tests;

use Esegments\LaravelExtensions\Contracts\ExtensionPointContract;
use Esegments\LaravelExtensions\HandlerRegistry;
use Esegments\LaravelExtensions\Tests\Fixtures\InterruptibleExtensionPoint;
use Esegments\LaravelExtensions\Tests\Fixtures\SimpleExtensionPoint;
use Esegments\LaravelExtensions\Tests\Fixtures\SimpleHandler;
use InvalidArgumentException;

final class HandlerRegistryTest extends TestCase
{
    private HandlerRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new HandlerRegistry();
    }

    public function test_can_register_handler_class(): void
    {
        $this->registry->register(SimpleExtensionPoint::class, SimpleHandler::class);

        $this->assertTrue($this->registry->hasHandlers(SimpleExtensionPoint::class));
        $this->assertCount(1, $this->registry->getHandlers(SimpleExtensionPoint::class));
    }

    public function test_can_register_callable_handler(): void
    {
        $this->registry->register(
            SimpleExtensionPoint::class,
            fn (SimpleExtensionPoint $ext) => $ext->processed[] = 'closure',
        );

        $this->assertTrue($this->registry->hasHandlers(SimpleExtensionPoint::class));
    }

    public function test_can_register_handler_with_priority(): void
    {
        $this->registry->register(SimpleExtensionPoint::class, 'HandlerA', 100);
        $this->registry->register(SimpleExtensionPoint::class, 'HandlerB', 50);
        $this->registry->register(SimpleExtensionPoint::class, 'HandlerC', 150);

        $handlers = $this->registry->getHandlers(SimpleExtensionPoint::class);

        $this->assertEquals(['HandlerB', 'HandlerA', 'HandlerC'], $handlers);
    }

    public function test_handlers_sorted_by_priority_lower_first(): void
    {
        $this->registry->register(SimpleExtensionPoint::class, 'Critical', 10);
        $this->registry->register(SimpleExtensionPoint::class, 'Normal', 100);
        $this->registry->register(SimpleExtensionPoint::class, 'Low', 200);

        $handlers = $this->registry->getHandlers(SimpleExtensionPoint::class);

        $this->assertEquals(['Critical', 'Normal', 'Low'], $handlers);
    }

    public function test_returns_empty_array_for_unregistered_extension_point(): void
    {
        $handlers = $this->registry->getHandlers(SimpleExtensionPoint::class);

        $this->assertEmpty($handlers);
    }

    public function test_has_handlers_returns_false_for_unregistered(): void
    {
        $this->assertFalse($this->registry->hasHandlers(SimpleExtensionPoint::class));
    }

    public function test_can_forget_handlers_for_extension_point(): void
    {
        $this->registry->register(SimpleExtensionPoint::class, SimpleHandler::class);
        $this->assertTrue($this->registry->hasHandlers(SimpleExtensionPoint::class));

        $this->registry->forget(SimpleExtensionPoint::class);

        $this->assertFalse($this->registry->hasHandlers(SimpleExtensionPoint::class));
    }

    public function test_can_clear_all_handlers(): void
    {
        $this->registry->register(SimpleExtensionPoint::class, SimpleHandler::class);
        $this->registry->register(InterruptibleExtensionPoint::class, SimpleHandler::class);

        $this->registry->clear();

        $this->assertFalse($this->registry->hasHandlers(SimpleExtensionPoint::class));
        $this->assertFalse($this->registry->hasHandlers(InterruptibleExtensionPoint::class));
    }

    public function test_can_get_registered_extension_points(): void
    {
        $this->registry->register(SimpleExtensionPoint::class, SimpleHandler::class);
        $this->registry->register(InterruptibleExtensionPoint::class, SimpleHandler::class);

        $points = $this->registry->getRegisteredExtensionPoints();

        $this->assertContains(SimpleExtensionPoint::class, $points);
        $this->assertContains(InterruptibleExtensionPoint::class, $points);
    }

    public function test_can_count_handlers(): void
    {
        $this->registry->register(SimpleExtensionPoint::class, 'Handler1');
        $this->registry->register(SimpleExtensionPoint::class, 'Handler2');
        $this->registry->register(SimpleExtensionPoint::class, 'Handler3');

        $this->assertEquals(3, $this->registry->countHandlers(SimpleExtensionPoint::class));
        $this->assertEquals(0, $this->registry->countHandlers(InterruptibleExtensionPoint::class));
    }

    public function test_can_get_handlers_with_priorities(): void
    {
        $this->registry->register(SimpleExtensionPoint::class, 'HandlerA', 100);
        $this->registry->register(SimpleExtensionPoint::class, 'HandlerB', 50);

        $handlers = $this->registry->getHandlersWithPriorities(SimpleExtensionPoint::class);

        $this->assertCount(2, $handlers);
        $this->assertEquals('HandlerB', $handlers[0]['handler']);
        $this->assertEquals(50, $handlers[0]['priority']);
        $this->assertEquals('HandlerA', $handlers[1]['handler']);
        $this->assertEquals(100, $handlers[1]['priority']);
    }

    public function test_throws_exception_for_invalid_extension_point_class(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must implement ExtensionPointContract');

        $this->registry->register('InvalidClass', SimpleHandler::class);
    }

    public function test_supports_fluent_interface(): void
    {
        $result = $this->registry
            ->register(SimpleExtensionPoint::class, 'Handler1')
            ->register(SimpleExtensionPoint::class, 'Handler2')
            ->forget(SimpleExtensionPoint::class);

        $this->assertInstanceOf(HandlerRegistry::class, $result);
    }

    public function test_caches_sorted_handlers(): void
    {
        $this->registry->register(SimpleExtensionPoint::class, 'HandlerA', 100);
        $this->registry->register(SimpleExtensionPoint::class, 'HandlerB', 50);

        // First call sorts and caches
        $handlers1 = $this->registry->getHandlers(SimpleExtensionPoint::class);
        // Second call returns from cache
        $handlers2 = $this->registry->getHandlers(SimpleExtensionPoint::class);

        $this->assertSame($handlers1, $handlers2);
    }

    public function test_invalidates_cache_on_new_registration(): void
    {
        $this->registry->register(SimpleExtensionPoint::class, 'HandlerA', 100);
        $this->registry->getHandlers(SimpleExtensionPoint::class);

        // New registration should invalidate cache
        $this->registry->register(SimpleExtensionPoint::class, 'HandlerB', 10);
        $handlers = $this->registry->getHandlers(SimpleExtensionPoint::class);

        $this->assertEquals(['HandlerB', 'HandlerA'], $handlers);
    }
}
