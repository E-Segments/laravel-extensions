<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Tests;

use Esegments\LaravelExtensions\Discovery\AttributeDiscovery;
use Esegments\LaravelExtensions\HandlerRegistry;
use Esegments\LaravelExtensions\Tests\Fixtures\AttributeHandler;
use Esegments\LaravelExtensions\Tests\Fixtures\SimpleExtension;

final class AttributeDiscoveryTest extends TestCase
{
    private HandlerRegistry $registry;

    private AttributeDiscovery $discovery;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new HandlerRegistry;
        $this->discovery = new AttributeDiscovery($this->registry);
    }

    public function test_can_discover_handlers_from_directory(): void
    {
        $fixturesPath = __DIR__.'/Fixtures';

        $handlers = $this->discovery->discover([$fixturesPath]);

        // Should find AttributeHandler which has the #[ExtensionHandler] attribute
        $this->assertNotEmpty($handlers);

        $found = false;
        foreach ($handlers as $handler) {
            if ($handler['handler'] === AttributeHandler::class) {
                $found = true;
                $this->assertEquals(SimpleExtension::class, $handler['extensionPoint']);
                $this->assertEquals(50, $handler['priority']);
                $this->assertFalse($handler['async']);
            }
        }

        $this->assertTrue($found, 'AttributeHandler should be discovered');
    }

    public function test_can_discover_and_register_handlers(): void
    {
        $fixturesPath = __DIR__.'/Fixtures';

        $count = $this->discovery->discoverAndRegister([$fixturesPath]);

        $this->assertGreaterThan(0, $count);
        $this->assertTrue($this->registry->hasHandlers(SimpleExtension::class));
    }

    public function test_skips_non_existent_directories(): void
    {
        $handlers = $this->discovery->discover(['/non/existent/path']);

        $this->assertEmpty($handlers);
    }

    public function test_get_discovered_handlers_returns_all_handlers(): void
    {
        $fixturesPath = __DIR__.'/Fixtures';

        $this->discovery->discover([$fixturesPath]);
        $handlers = $this->discovery->getDiscoveredHandlers();

        $this->assertIsArray($handlers);
    }
}
