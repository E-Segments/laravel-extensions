<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Tests;

use Esegments\LaravelExtensions\Tests\Fixtures\InterruptibleExtension;

final class InterruptibleTest extends TestCase
{
    public function test_interruptible_extension_is_not_interrupted_by_default(): void
    {
        $extension = new InterruptibleExtension;

        $this->assertFalse($extension->wasInterrupted());
        $this->assertNull($extension->getInterruptedBy());
    }

    public function test_can_interrupt_extension(): void
    {
        $extension = new InterruptibleExtension;

        $extension->interrupt();

        $this->assertTrue($extension->wasInterrupted());
    }

    public function test_can_set_interrupted_by(): void
    {
        $extension = new InterruptibleExtension;

        $extension->interrupt();
        $extension->setInterruptedBy('SomeHandler');

        $this->assertEquals('SomeHandler', $extension->getInterruptedBy());
    }

    public function test_errors_can_be_added(): void
    {
        $extension = new InterruptibleExtension;

        $extension->addError('Error 1');
        $extension->addError('Error 2');

        $this->assertTrue($extension->hasErrors());
        $this->assertCount(2, $extension->errors);
        $this->assertEquals(['Error 1', 'Error 2'], $extension->errors);
    }

    public function test_has_errors_returns_false_when_no_errors(): void
    {
        $extension = new InterruptibleExtension;

        $this->assertFalse($extension->hasErrors());
    }

    public function test_processed_count_starts_at_zero(): void
    {
        $extension = new InterruptibleExtension;

        $this->assertEquals(0, $extension->processedCount);
    }

    public function test_can_increment_processed_count(): void
    {
        $extension = new InterruptibleExtension;

        $extension->incrementProcessed();
        $extension->incrementProcessed();

        $this->assertEquals(2, $extension->processedCount);
    }

    public function test_constructor_sets_readonly_properties(): void
    {
        $extension = new InterruptibleExtension(
            orderId: 'order-123',
            total: 250.50,
        );

        $this->assertEquals('order-123', $extension->orderId);
        $this->assertEquals(250.50, $extension->total);
    }
}
