<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Logging;

use Esegments\LaravelExtensions\Support\DebugInfo;
use Illuminate\Support\Facades\Log;

/**
 * Logger for extension point debugging.
 *
 * Logs extension point dispatches, handler executions, and performance metrics.
 */
final class ExtensionLogger
{
    public function __construct(
        private readonly ?string $channel = null,
    ) {}

    /**
     * Log an extension point dispatch.
     */
    public function logDispatch(DebugInfo $debugInfo): void
    {
        $logger = $this->channel ? Log::channel($this->channel) : Log::getFacadeRoot();

        $message = sprintf(
            '[Extensions] Dispatched %s (%d handlers, %.2fms%s)',
            $debugInfo->extensionPointClass,
            count($debugInfo->handlerExecutions),
            $debugInfo->totalDurationMs,
            $debugInfo->wasInterrupted ? ', INTERRUPTED by '.$debugInfo->interruptedBy : ''
        );

        $logger->debug($message, $debugInfo->toArray());
    }

    /**
     * Log a handler error.
     */
    public function logHandlerError(
        string $extensionPointClass,
        string $handlerClass,
        \Throwable $error,
    ): void {
        $logger = $this->channel ? Log::channel($this->channel) : Log::getFacadeRoot();

        $logger->error(
            sprintf('[Extensions] Handler error in %s for %s', $handlerClass, $extensionPointClass),
            [
                'extension_point' => $extensionPointClass,
                'handler' => $handlerClass,
                'error' => $error->getMessage(),
                'trace' => $error->getTraceAsString(),
            ]
        );
    }

    /**
     * Log handler registration.
     */
    public function logRegistration(
        string $extensionPointClass,
        string $handlerClass,
        int $priority,
    ): void {
        $logger = $this->channel ? Log::channel($this->channel) : Log::getFacadeRoot();

        $logger->debug(
            sprintf('[Extensions] Registered %s for %s (priority: %d)', $handlerClass, $extensionPointClass, $priority),
            [
                'extension_point' => $extensionPointClass,
                'handler' => $handlerClass,
                'priority' => $priority,
            ]
        );
    }

    /**
     * Log discovered handlers.
     */
    public function logDiscovery(int $count, array $directories): void
    {
        $logger = $this->channel ? Log::channel($this->channel) : Log::getFacadeRoot();

        $logger->info(
            sprintf('[Extensions] Discovered %d handlers from %d directories', $count, count($directories)),
            [
                'handler_count' => $count,
                'directories' => $directories,
            ]
        );
    }
}
