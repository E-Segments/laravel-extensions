<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Tests;

use Esegments\LaravelExtensions\Contracts\ExtensionHandlerContract;
use Esegments\LaravelExtensions\Contracts\ExtensionPointContract;
use Esegments\LaravelExtensions\Exceptions\SignatureMismatchException;
use Esegments\LaravelExtensions\Profiling\ExecutionProfiler;
use Esegments\LaravelExtensions\Profiling\HandlerProfile;
use Esegments\LaravelExtensions\Profiling\ProfileResult;
use Esegments\LaravelExtensions\Tests\Fixtures\SimpleExtensionPoint;
use Esegments\LaravelExtensions\Tests\Fixtures\SimpleHandler;
use Esegments\LaravelExtensions\Validation\SignatureValidator;

class DXFeaturesTest extends TestCase
{
    // =========================================
    // HandlerProfile Tests
    // =========================================

    public function test_handler_profile_creation(): void
    {
        $profile = new HandlerProfile(
            handlerClass: 'TestHandler',
            executionTimeMs: 50.5,
            result: 'success',
        );

        $this->assertEquals('TestHandler', $profile->handlerClass);
        $this->assertEquals(50.5, $profile->executionTimeMs);
        $this->assertEquals('success', $profile->result);
    }

    public function test_handler_profile_is_successful(): void
    {
        $successProfile = new HandlerProfile(
            handlerClass: 'TestHandler',
            executionTimeMs: 10.0,
            result: 'ok',
        );

        $errorProfile = new HandlerProfile(
            handlerClass: 'TestHandler',
            executionTimeMs: 10.0,
            result: null,
            error: 'Something went wrong',
        );

        $skippedProfile = new HandlerProfile(
            handlerClass: 'TestHandler',
            executionTimeMs: 0.0,
            result: null,
            skipped: true,
            skipReason: 'Handler muted',
        );

        $this->assertTrue($successProfile->isSuccessful());
        $this->assertFalse($errorProfile->isSuccessful());
        $this->assertFalse($skippedProfile->isSuccessful());
    }

    public function test_handler_profile_is_slow(): void
    {
        $fastProfile = new HandlerProfile(
            handlerClass: 'FastHandler',
            executionTimeMs: 50.0,
            result: null,
        );

        $slowProfile = new HandlerProfile(
            handlerClass: 'SlowHandler',
            executionTimeMs: 150.0,
            result: null,
        );

        $this->assertFalse($fastProfile->isSlow(100));
        $this->assertTrue($slowProfile->isSlow(100));
    }

    public function test_handler_profile_formatted_time(): void
    {
        $microseconds = new HandlerProfile('Test', 0.5, null);
        $milliseconds = new HandlerProfile('Test', 50.0, null);
        $seconds = new HandlerProfile('Test', 1500.0, null);

        $this->assertStringContainsString('μs', $microseconds->formattedTime());
        $this->assertStringContainsString('ms', $milliseconds->formattedTime());
        $this->assertStringContainsString('s', $seconds->formattedTime());
    }

    public function test_handler_profile_formatted_memory(): void
    {
        $bytes = new HandlerProfile('Test', 10.0, null, memoryUsageBytes: 512);
        $kilobytes = new HandlerProfile('Test', 10.0, null, memoryUsageBytes: 2048);
        $megabytes = new HandlerProfile('Test', 10.0, null, memoryUsageBytes: 1048576 * 2);

        $this->assertStringContainsString('B', $bytes->formattedMemory());
        $this->assertStringContainsString('KB', $kilobytes->formattedMemory());
        $this->assertStringContainsString('MB', $megabytes->formattedMemory());
    }

    public function test_handler_profile_to_array(): void
    {
        $profile = new HandlerProfile(
            handlerClass: 'TestHandler',
            executionTimeMs: 25.5,
            result: 'success',
            memoryUsageBytes: 1024,
        );

        $array = $profile->toArray();

        $this->assertArrayHasKey('handler', $array);
        $this->assertArrayHasKey('execution_time_ms', $array);
        $this->assertArrayHasKey('memory_bytes', $array);
        $this->assertArrayHasKey('successful', $array);
        $this->assertEquals('TestHandler', $array['handler']);
        $this->assertEquals(25.5, $array['execution_time_ms']);
    }

    public function test_handler_profile_uses_makeable_trait(): void
    {
        $profile = HandlerProfile::make(
            handlerClass: 'TestHandler',
            executionTimeMs: 10.0,
            result: 'test',
        );

        $this->assertInstanceOf(HandlerProfile::class, $profile);
    }

    // =========================================
    // ProfileResult Tests
    // =========================================

    public function test_profile_result_records_handlers(): void
    {
        $result = new ProfileResult(
            extensionPointClass: SimpleExtensionPoint::class,
            startTime: microtime(true),
        );

        $result->recordHandler(new HandlerProfile('Handler1', 10.0, null));
        $result->recordHandler(new HandlerProfile('Handler2', 20.0, null));

        $this->assertCount(2, $result->handlers());
    }

    public function test_profile_result_calculates_total_time(): void
    {
        $startTime = microtime(true);
        $result = new ProfileResult(
            extensionPointClass: SimpleExtensionPoint::class,
            startTime: $startTime,
        );

        usleep(10000); // 10ms
        $result->complete();

        $this->assertGreaterThan(0, $result->totalTime());
    }

    public function test_profile_result_finds_slowest_handler(): void
    {
        $result = new ProfileResult(SimpleExtensionPoint::class, microtime(true));

        $result->recordHandler(new HandlerProfile('Fast', 10.0, null));
        $result->recordHandler(new HandlerProfile('Slow', 100.0, null));
        $result->recordHandler(new HandlerProfile('Medium', 50.0, null));

        $slowest = $result->slowest();

        $this->assertNotNull($slowest);
        $this->assertEquals('Slow', $slowest->handlerClass);
    }

    public function test_profile_result_finds_slow_handlers(): void
    {
        $result = new ProfileResult(SimpleExtensionPoint::class, microtime(true));

        $result->recordHandler(new HandlerProfile('Fast', 10.0, null));
        $result->recordHandler(new HandlerProfile('Slow1', 150.0, null));
        $result->recordHandler(new HandlerProfile('Slow2', 200.0, null));

        $slowHandlers = $result->slowHandlers(100.0);

        $this->assertCount(2, $slowHandlers);
    }

    public function test_profile_result_finds_failed_handlers(): void
    {
        $result = new ProfileResult(SimpleExtensionPoint::class, microtime(true));

        $result->recordHandler(new HandlerProfile('Success', 10.0, null));
        $result->recordHandler(new HandlerProfile('Failed', 10.0, null, error: 'Error!'));

        $failed = $result->failedHandlers();

        $this->assertCount(1, $failed);
    }

    public function test_profile_result_finds_skipped_handlers(): void
    {
        $result = new ProfileResult(SimpleExtensionPoint::class, microtime(true));

        $result->recordHandler(new HandlerProfile('Run', 10.0, null));
        $result->recordHandler(new HandlerProfile('Skipped', 0.0, null, skipped: true));

        $skipped = $result->skippedHandlers();

        $this->assertCount(1, $skipped);
    }

    public function test_profile_result_has_slow(): void
    {
        $result = new ProfileResult(SimpleExtensionPoint::class, microtime(true));
        $result->recordHandler(new HandlerProfile('Fast', 10.0, null));

        $this->assertFalse($result->hasSlow(100.0));

        $result->recordHandler(new HandlerProfile('Slow', 150.0, null));

        $this->assertTrue($result->hasSlow(100.0));
    }

    public function test_profile_result_has_errors(): void
    {
        $result = new ProfileResult(SimpleExtensionPoint::class, microtime(true));
        $result->recordHandler(new HandlerProfile('Success', 10.0, null));

        $this->assertFalse($result->hasErrors());

        $result->recordHandler(new HandlerProfile('Failed', 10.0, null, error: 'Error'));

        $this->assertTrue($result->hasErrors());
    }

    public function test_profile_result_to_array(): void
    {
        $result = new ProfileResult(SimpleExtensionPoint::class, microtime(true));
        $result->recordHandler(new HandlerProfile('Handler', 10.0, null));
        $result->complete();

        $array = $result->toArray();

        $this->assertArrayHasKey('extension_point', $array);
        $this->assertArrayHasKey('total_time_ms', $array);
        $this->assertArrayHasKey('handler_count', $array);
        $this->assertEquals(1, $array['handler_count']);
    }

    public function test_profile_result_formatted_time(): void
    {
        $result = new ProfileResult(SimpleExtensionPoint::class, microtime(true));
        usleep(1000); // 1ms
        $result->complete();

        $formatted = $result->formattedTotalTime();

        $this->assertIsString($formatted);
        // Should contain time unit
        $this->assertMatchesRegularExpression('/[μms]/', $formatted);
    }

    // =========================================
    // ExecutionProfiler Tests
    // =========================================

    public function test_profiler_is_disabled_by_default(): void
    {
        $profiler = new ExecutionProfiler();

        $this->assertFalse($profiler->isEnabled());
    }

    public function test_profiler_can_be_enabled(): void
    {
        $profiler = new ExecutionProfiler(enabled: true);

        $this->assertTrue($profiler->isEnabled());
    }

    public function test_profiler_enable_disable(): void
    {
        $profiler = new ExecutionProfiler();

        $profiler->enable();
        $this->assertTrue($profiler->isEnabled());

        $profiler->disable();
        $this->assertFalse($profiler->isEnabled());
    }

    public function test_profiler_starts_profiling(): void
    {
        $profiler = new ExecutionProfiler();
        $extensionPoint = new SimpleExtensionPoint();

        $result = $profiler->start($extensionPoint);

        $this->assertInstanceOf(ProfileResult::class, $result);
        $this->assertEquals(SimpleExtensionPoint::class, $result->extensionPointClass);
    }

    public function test_profiler_records_handler(): void
    {
        $profiler = new ExecutionProfiler();
        $extensionPoint = new SimpleExtensionPoint();

        $result = $profiler->start($extensionPoint);
        $profile = new HandlerProfile('TestHandler', 10.0, null);
        $profiler->recordHandler($result, $profile);

        $this->assertCount(1, $result->handlers());
    }

    public function test_profiler_completes_profiling(): void
    {
        $profiler = new ExecutionProfiler();
        $extensionPoint = new SimpleExtensionPoint();

        $result = $profiler->start($extensionPoint);
        $completed = $profiler->complete($result);

        $this->assertSame($result, $completed);
        $this->assertGreaterThanOrEqual(0, $result->totalTime());
    }

    public function test_profiler_stores_last_profile(): void
    {
        $profiler = new ExecutionProfiler();
        $extensionPoint = new SimpleExtensionPoint();

        $this->assertNull($profiler->getLastProfile());

        $result = $profiler->start($extensionPoint);
        $profiler->complete($result);

        $this->assertSame($result, $profiler->getLastProfile());
    }

    public function test_profiler_slow_threshold(): void
    {
        $profiler = new ExecutionProfiler(slowThreshold: 50.0);

        $this->assertEquals(50.0, $profiler->getSlowThreshold());

        $profiler->setSlowThreshold(150.0);

        $this->assertEquals(150.0, $profiler->getSlowThreshold());
    }

    public function test_profiler_profile_callback(): void
    {
        $profiler = new ExecutionProfiler();

        $result = $profiler->profileCallback('TestCallback', function () {
            return 'callback result';
        });

        $this->assertArrayHasKey('result', $result);
        $this->assertArrayHasKey('profile', $result);
        $this->assertEquals('callback result', $result['result']);
        $this->assertInstanceOf(HandlerProfile::class, $result['profile']);
    }

    // =========================================
    // SignatureValidator Tests
    // =========================================

    public function test_validator_validates_handler_contract(): void
    {
        $validator = new SignatureValidator;

        // SimpleHandler implements ExtensionHandlerContract
        $result = $validator->validate(SimpleHandler::class, SimpleExtensionPoint::class);

        $this->assertTrue($result);
    }

    public function test_validator_returns_false_for_nonexistent_class(): void
    {
        $validator = new SignatureValidator;

        $result = $validator->validate('NonExistentHandler', SimpleExtensionPoint::class);

        $this->assertFalse($result);
    }

    public function test_validator_throws_for_missing_handle_method(): void
    {
        $validator = new SignatureValidator;

        // stdClass has no handle or __invoke method
        $this->expectException(SignatureMismatchException::class);

        $validator->validate(\stdClass::class, SimpleExtensionPoint::class);
    }

    public function test_validator_validates_invokable_class(): void
    {
        $validator = new SignatureValidator;

        // Create an anonymous invokable class
        $invokable = new class
        {
            public function __invoke(SimpleExtensionPoint $event): void {}
        };

        $result = $validator->validate($invokable::class, SimpleExtensionPoint::class);

        $this->assertTrue($result);
    }

    public function test_validator_get_expected_signature(): void
    {
        $validator = new SignatureValidator;

        $signature = $validator->getExpectedSignature(SimpleExtensionPoint::class);

        $this->assertStringContainsString('SimpleExtensionPoint', $signature);
        $this->assertStringContainsString('handle', $signature);
    }

    public function test_validator_allows_extension_point_contract_type(): void
    {
        $validator = new SignatureValidator;

        $genericHandler = new class implements ExtensionHandlerContract
        {
            public function handle(ExtensionPointContract $event): mixed { return null; }
        };

        $result = $validator->validate($genericHandler::class, SimpleExtensionPoint::class);

        $this->assertTrue($result);
    }

    // =========================================
    // SignatureMismatchException Tests
    // =========================================

    public function test_signature_mismatch_exception_mismatch(): void
    {
        $exception = SignatureMismatchException::mismatch(
            'TestHandler',
            'ExpectedExtension',
            'ExpectedExtension',
            'WrongType',
        );

        $this->assertStringContainsString('TestHandler', $exception->getMessage());
        $this->assertStringContainsString('ExpectedExtension', $exception->getMessage());
    }

    public function test_signature_mismatch_exception_missing_handle(): void
    {
        $exception = SignatureMismatchException::missingHandleMethod('TestHandler');

        $this->assertStringContainsString('TestHandler', $exception->getMessage());
        $this->assertStringContainsString('handle', $exception->getMessage());
    }
}
