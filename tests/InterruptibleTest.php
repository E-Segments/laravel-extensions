<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Tests;

use Esegments\LaravelExtensions\Tests\Fixtures\InterruptibleExtensionPoint;

final class InterruptibleTest extends TestCase
{
    public function test_extension_point_starts_not_interrupted(): void
    {
        $extensionPoint = new InterruptibleExtensionPoint();

        $this->assertFalse($extensionPoint->wasInterrupted());
        $this->assertNull($extensionPoint->getInterruptedBy());
    }

    public function test_can_interrupt_extension_point(): void
    {
        $extensionPoint = new InterruptibleExtensionPoint();

        $extensionPoint->interrupt();

        $this->assertTrue($extensionPoint->wasInterrupted());
    }

    public function test_can_set_interrupted_by(): void
    {
        $extensionPoint = new InterruptibleExtensionPoint();

        $extensionPoint->interrupt();
        $extensionPoint->setInterruptedBy('SomeHandler');

        $this->assertTrue($extensionPoint->wasInterrupted());
        $this->assertEquals('SomeHandler', $extensionPoint->getInterruptedBy());
    }

    public function test_can_add_errors(): void
    {
        $extensionPoint = new InterruptibleExtensionPoint();

        $extensionPoint->addError('Error 1');
        $extensionPoint->addError('Error 2');

        $this->assertTrue($extensionPoint->hasErrors());
        $this->assertEquals(['Error 1', 'Error 2'], $extensionPoint->errors);
    }

    public function test_has_no_errors_initially(): void
    {
        $extensionPoint = new InterruptibleExtensionPoint();

        $this->assertFalse($extensionPoint->hasErrors());
        $this->assertEmpty($extensionPoint->errors);
    }

    public function test_preserves_readonly_data(): void
    {
        $extensionPoint = new InterruptibleExtensionPoint(data: 'custom_data');

        $this->assertEquals('custom_data', $extensionPoint->data);
    }
}
