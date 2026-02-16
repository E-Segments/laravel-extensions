<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Integrations\Pulse;

use Esegments\LaravelExtensions\Profiling\ProfileResult;

/**
 * Pulse recorder for extension points.
 *
 * Records extension point metrics for Laravel Pulse dashboard.
 */
class ExtensionRecorder
{
    /**
     * Record a dispatch to Pulse.
     */
    public function record(ProfileResult $result): void
    {
        // Only record if Pulse is available
        if (! function_exists('pulse')) {
            return;
        }

        // Record dispatch
        pulse()
            ->record(
                type: 'extension_dispatch',
                key: $result->extensionPointClass,
                value: $result->totalTime(),
                timestamp: now(),
            )
            ->count();

        // Record slow handlers
        foreach ($result->slowHandlers() as $handler) {
            pulse()
                ->record(
                    type: 'extension_slow_handler',
                    key: $handler->handlerClass,
                    value: $handler->executionTimeMs,
                    timestamp: now(),
                )
                ->count();
        }

        // Record errors
        foreach ($result->failedHandlers() as $handler) {
            pulse()
                ->record(
                    type: 'extension_error',
                    key: $handler->handlerClass,
                    value: 1,
                    timestamp: now(),
                )
                ->count();
        }
    }
}
