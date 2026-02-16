<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Tests;

use Esegments\LaravelExtensions\Contracts\ExtensionPointContract;
use Esegments\LaravelExtensions\Exceptions\StrictModeException;
use Esegments\LaravelExtensions\ExtensionDispatcher;
use Esegments\LaravelExtensions\Results\DispatchResult;
use Exception;
use RuntimeException;

class SafetyFeaturesTest extends TestCase
{
    // =========================================
    // GracefulExecution Tests
    // =========================================

    public function test_graceful_mode_is_disabled_by_default(): void
    {
        $dispatcher = $this->app->make(ExtensionDispatcher::class);

        $this->assertFalse($dispatcher->isGracefulMode());
    }

    public function test_gracefully_enables_graceful_mode(): void
    {
        $dispatcher = $this->app->make(ExtensionDispatcher::class);

        $dispatcher->gracefully();

        $this->assertTrue($dispatcher->isGracefulMode());
    }

    public function test_strictly_disables_graceful_mode(): void
    {
        $dispatcher = $this->app->make(ExtensionDispatcher::class);

        $dispatcher->gracefully();
        $this->assertTrue($dispatcher->isGracefulMode());

        $dispatcher->strictly();
        $this->assertFalse($dispatcher->isGracefulMode());
    }

    public function test_graceful_mode_is_fluent(): void
    {
        $dispatcher = $this->app->make(ExtensionDispatcher::class);

        $result = $dispatcher->gracefully();

        $this->assertSame($dispatcher, $result);
    }

    public function test_reset_graceful_mode_uses_config_default(): void
    {
        config(['extensions.graceful_mode' => true]);

        $dispatcher = $this->app->make(ExtensionDispatcher::class);
        $dispatcher->strictly(); // Disable it
        $this->assertFalse($dispatcher->isGracefulMode());

        $dispatcher->resetGracefulMode();

        $this->assertTrue($dispatcher->isGracefulMode());
    }

    // =========================================
    // Mutable Tests
    // =========================================

    public function test_mute_handler(): void
    {
        $dispatcher = $this->app->make(ExtensionDispatcher::class);

        $dispatcher->mute('TestHandler');

        $this->assertTrue($dispatcher->isMuted('TestHandler'));
    }

    public function test_unmute_handler(): void
    {
        $dispatcher = $this->app->make(ExtensionDispatcher::class);

        $dispatcher->mute('TestHandler');
        $this->assertTrue($dispatcher->isMuted('TestHandler'));

        $dispatcher->unmute('TestHandler');

        $this->assertFalse($dispatcher->isMuted('TestHandler'));
    }

    public function test_get_muted_handlers(): void
    {
        $dispatcher = $this->app->make(ExtensionDispatcher::class);

        $dispatcher->mute('HandlerA');
        $dispatcher->mute('HandlerB');
        $dispatcher->mute('HandlerC');

        $muted = $dispatcher->getMutedHandlers();

        $this->assertCount(3, $muted);
        $this->assertContains('HandlerA', $muted);
        $this->assertContains('HandlerB', $muted);
        $this->assertContains('HandlerC', $muted);
    }

    public function test_clear_muted_handlers(): void
    {
        $dispatcher = $this->app->make(ExtensionDispatcher::class);

        $dispatcher->mute('HandlerA');
        $dispatcher->mute('HandlerB');
        $this->assertCount(2, $dispatcher->getMutedHandlers());

        $dispatcher->clearMuted();

        $this->assertEmpty($dispatcher->getMutedHandlers());
    }

    public function test_mute_is_fluent(): void
    {
        $dispatcher = $this->app->make(ExtensionDispatcher::class);

        $result = $dispatcher->mute('TestHandler');

        $this->assertSame($dispatcher, $result);
    }

    public function test_with_muted_executes_callback_with_handler_muted(): void
    {
        $dispatcher = $this->app->make(ExtensionDispatcher::class);
        $executed = false;

        $result = $dispatcher->withMuted('TestHandler', function () use ($dispatcher, &$executed) {
            $executed = true;
            $this->assertTrue($dispatcher->isMuted('TestHandler'));

            return 'callback result';
        });

        $this->assertTrue($executed);
        $this->assertEquals('callback result', $result);
        $this->assertFalse($dispatcher->isMuted('TestHandler')); // Restored after callback
    }

    public function test_with_muted_restores_previous_state_on_exception(): void
    {
        $dispatcher = $this->app->make(ExtensionDispatcher::class);

        try {
            $dispatcher->withMuted('TestHandler', function () {
                throw new RuntimeException('Test exception');
            });
        } catch (RuntimeException) {
            // Expected
        }

        $this->assertFalse($dispatcher->isMuted('TestHandler'));
    }

    public function test_with_muted_preserves_already_muted_state(): void
    {
        $dispatcher = $this->app->make(ExtensionDispatcher::class);
        $dispatcher->mute('TestHandler');

        $dispatcher->withMuted('TestHandler', function () use ($dispatcher) {
            $this->assertTrue($dispatcher->isMuted('TestHandler'));
        });

        // Should still be muted because it was muted before
        $this->assertTrue($dispatcher->isMuted('TestHandler'));
    }

    public function test_with_muted_many_mutes_multiple_handlers(): void
    {
        $dispatcher = $this->app->make(ExtensionDispatcher::class);

        $dispatcher->withMutedMany(['HandlerA', 'HandlerB', 'HandlerC'], function () use ($dispatcher) {
            $this->assertTrue($dispatcher->isMuted('HandlerA'));
            $this->assertTrue($dispatcher->isMuted('HandlerB'));
            $this->assertTrue($dispatcher->isMuted('HandlerC'));
        });

        $this->assertFalse($dispatcher->isMuted('HandlerA'));
        $this->assertFalse($dispatcher->isMuted('HandlerB'));
        $this->assertFalse($dispatcher->isMuted('HandlerC'));
    }

    // =========================================
    // Silenceable Tests
    // =========================================

    public function test_silenced_is_false_by_default(): void
    {
        $dispatcher = $this->app->make(ExtensionDispatcher::class);

        $this->assertFalse($dispatcher->isSilenced());
    }

    public function test_silence_all_enables_silencing(): void
    {
        $dispatcher = $this->app->make(ExtensionDispatcher::class);

        $dispatcher->silenceAll();

        $this->assertTrue($dispatcher->isSilenced());
    }

    public function test_resume_all_disables_silencing(): void
    {
        $dispatcher = $this->app->make(ExtensionDispatcher::class);

        $dispatcher->silenceAll();
        $this->assertTrue($dispatcher->isSilenced());

        $dispatcher->resumeAll();

        $this->assertFalse($dispatcher->isSilenced());
    }

    public function test_silence_all_is_fluent(): void
    {
        $dispatcher = $this->app->make(ExtensionDispatcher::class);

        $result = $dispatcher->silenceAll();

        $this->assertSame($dispatcher, $result);
    }

    public function test_silence_executes_callback_while_silenced(): void
    {
        $dispatcher = $this->app->make(ExtensionDispatcher::class);
        $executed = false;

        $result = $dispatcher->silence(function () use ($dispatcher, &$executed) {
            $executed = true;
            $this->assertTrue($dispatcher->isSilenced());

            return 'silent result';
        });

        $this->assertTrue($executed);
        $this->assertEquals('silent result', $result);
        $this->assertFalse($dispatcher->isSilenced()); // Restored after callback
    }

    public function test_silence_restores_state_on_exception(): void
    {
        $dispatcher = $this->app->make(ExtensionDispatcher::class);

        try {
            $dispatcher->silence(function () {
                throw new RuntimeException('Test exception');
            });
        } catch (RuntimeException) {
            // Expected
        }

        $this->assertFalse($dispatcher->isSilenced());
    }

    public function test_silence_preserves_already_silenced_state(): void
    {
        $dispatcher = $this->app->make(ExtensionDispatcher::class);
        $dispatcher->silenceAll();

        $dispatcher->silence(function () use ($dispatcher) {
            $this->assertTrue($dispatcher->isSilenced());
        });

        // Should still be silenced because it was silenced before
        $this->assertTrue($dispatcher->isSilenced());
    }

    // =========================================
    // StrictModeException Tests
    // =========================================

    public function test_strict_mode_exception_for_unregistered_extension_point(): void
    {
        $exception = StrictModeException::unregisteredExtensionPoint('App\\Extensions\\UnknownPoint');

        $this->assertStringContainsString('No handlers registered', $exception->getMessage());
        $this->assertStringContainsString('UnknownPoint', $exception->getMessage());
        $this->assertEquals('EXTENSION_STRICT_MODE', $exception->getErrorCode());
    }

    public function test_strict_mode_exception_for_unknown_handler(): void
    {
        $exception = StrictModeException::unknownHandler('App\\Handlers\\UnknownHandler');

        $this->assertStringContainsString('does not exist', $exception->getMessage());
        $this->assertStringContainsString('UnknownHandler', $exception->getMessage());
    }

    public function test_strict_mode_exception_for_invalid_extension_point(): void
    {
        $exception = StrictModeException::invalidExtensionPoint('App\\NotAnExtensionPoint');

        $this->assertStringContainsString('does not implement ExtensionPointContract', $exception->getMessage());
        $this->assertStringContainsString('NotAnExtensionPoint', $exception->getMessage());
    }

    public function test_strict_mode_exception_has_context(): void
    {
        $exception = StrictModeException::unregisteredExtensionPoint('TestPoint');

        $context = $exception->getContext();

        $this->assertArrayHasKey('extension_point', $context);
        $this->assertEquals('TestPoint', $context['extension_point']);
    }

    // =========================================
    // DispatchResult Tests
    // =========================================

    public function test_dispatch_result_records_success(): void
    {
        $extensionPoint = $this->createMock(ExtensionPointContract::class);
        $result = new DispatchResult($extensionPoint);

        $result->recordSuccess('HandlerA', 'result A');
        $result->recordSuccess('HandlerB', 'result B');

        $this->assertCount(2, $result->results());
        $this->assertCount(2, $result->successful());
        $this->assertEquals('result A', $result->resultFor('HandlerA'));
        $this->assertEquals('result B', $result->resultFor('HandlerB'));
    }

    public function test_dispatch_result_records_errors(): void
    {
        $extensionPoint = $this->createMock(ExtensionPointContract::class);
        $result = new DispatchResult($extensionPoint);

        $error = new Exception('Test error');
        $result->recordError('FailingHandler', $error);

        $this->assertTrue($result->hasErrors());
        $this->assertCount(1, $result->errors());
        $this->assertSame($error, $result->errorFor('FailingHandler'));
    }

    public function test_dispatch_result_records_skipped(): void
    {
        $extensionPoint = $this->createMock(ExtensionPointContract::class);
        $result = new DispatchResult($extensionPoint);

        $result->recordSkipped('SkippedHandler', 'Handler is muted');

        $this->assertCount(1, $result->skipped());
        $this->assertEquals('Handler is muted', $result->skipped()->get('SkippedHandler'));
    }

    public function test_dispatch_result_is_successful_when_no_errors(): void
    {
        $extensionPoint = $this->createMock(ExtensionPointContract::class);
        $result = new DispatchResult($extensionPoint);

        $result->recordSuccess('Handler', 'success');

        $this->assertTrue($result->isSuccessful());
    }

    public function test_dispatch_result_is_not_successful_with_errors(): void
    {
        $extensionPoint = $this->createMock(ExtensionPointContract::class);
        $result = new DispatchResult($extensionPoint);

        $result->recordError('Handler', new Exception('error'));

        $this->assertFalse($result->isSuccessful());
    }

    public function test_dispatch_result_is_not_successful_when_interrupted(): void
    {
        $extensionPoint = $this->createMock(ExtensionPointContract::class);
        $result = new DispatchResult($extensionPoint, wasInterrupted: true, interruptedBy: 'InterruptingHandler');

        $this->assertFalse($result->isSuccessful());
        $this->assertTrue($result->wasInterrupted());
        $this->assertEquals('InterruptingHandler', $result->interruptedBy());
    }

    public function test_dispatch_result_throw_on_error(): void
    {
        $extensionPoint = $this->createMock(ExtensionPointContract::class);
        $result = new DispatchResult($extensionPoint);
        $exception = new Exception('Test error');
        $result->recordError('Handler', $exception);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Test error');

        $result->throwOnError();
    }

    public function test_dispatch_result_throw_on_error_returns_self_when_no_errors(): void
    {
        $extensionPoint = $this->createMock(ExtensionPointContract::class);
        $result = new DispatchResult($extensionPoint);
        $result->recordSuccess('Handler', 'success');

        $returned = $result->throwOnError();

        $this->assertSame($result, $returned);
    }

    public function test_dispatch_result_first_result(): void
    {
        $extensionPoint = $this->createMock(ExtensionPointContract::class);
        $result = new DispatchResult($extensionPoint);

        $result->recordSuccess('HandlerA', 'first');
        $result->recordSuccess('HandlerB', 'second');

        $this->assertEquals('first', $result->firstResult());
    }

    public function test_dispatch_result_to_array(): void
    {
        $extensionPoint = $this->createMock(ExtensionPointContract::class);
        $result = new DispatchResult($extensionPoint, wasInterrupted: true, interruptedBy: 'Handler');

        $result->recordSuccess('SuccessHandler', 'success');
        $result->recordError('ErrorHandler', new Exception('error message'));
        $result->recordSkipped('SkippedHandler', 'muted');

        $array = $result->toArray();

        $this->assertArrayHasKey('successful', $array);
        $this->assertArrayHasKey('errors', $array);
        $this->assertArrayHasKey('skipped', $array);
        $this->assertArrayHasKey('was_interrupted', $array);
        $this->assertArrayHasKey('interrupted_by', $array);
        $this->assertTrue($array['was_interrupted']);
        $this->assertEquals('Handler', $array['interrupted_by']);
        $this->assertContains('SuccessHandler', $array['successful']);
        $this->assertEquals('error message', $array['errors']['ErrorHandler']);
        $this->assertEquals('muted', $array['skipped']['SkippedHandler']);
    }

    public function test_dispatch_result_uses_makeable_trait(): void
    {
        $extensionPoint = $this->createMock(ExtensionPointContract::class);

        $result = DispatchResult::make(
            extensionPoint: $extensionPoint,
        );

        $this->assertInstanceOf(DispatchResult::class, $result);
    }
}
