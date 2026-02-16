<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Integrations\Debugbar;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;
use Esegments\LaravelExtensions\Profiling\ProfileResult;

/**
 * Debugbar data collector for extension points.
 *
 * Collects and displays extension point dispatch data in Laravel Debugbar.
 */
class ExtensionCollector extends DataCollector implements Renderable
{
    /**
     * @var array<ProfileResult>
     */
    protected array $dispatches = [];

    /**
     * @var int
     */
    protected int $totalHandlers = 0;

    /**
     * @var float
     */
    protected float $totalTime = 0;

    /**
     * Record a dispatch.
     */
    public function addDispatch(ProfileResult $result): void
    {
        $this->dispatches[] = $result;
        $this->totalHandlers += $result->handlers()->count();
        $this->totalTime += $result->totalTime();
    }

    /**
     * @return array<string, mixed>
     */
    public function collect(): array
    {
        $data = [];

        foreach ($this->dispatches as $index => $result) {
            $data["dispatch_{$index}"] = [
                'extension_point' => $result->extensionPointClass,
                'total_time_ms' => round($result->totalTime(), 2),
                'memory_delta' => $result->formattedMemory(),
                'handlers' => $result->handlers()->map(fn ($h) => [
                    'class' => $h->handlerClass,
                    'time_ms' => round($h->executionTimeMs, 2),
                    'status' => $h->skipped ? "skipped ({$h->skipReason})" : ($h->error ? 'error' : 'success'),
                ])->all(),
            ];
        }

        return [
            'dispatches' => $data,
            'total_dispatches' => count($this->dispatches),
            'total_handlers' => $this->totalHandlers,
            'total_time_ms' => round($this->totalTime, 2),
        ];
    }

    /**
     * Get the collector name.
     */
    public function getName(): string
    {
        return 'extensions';
    }

    /**
     * Get the widget data.
     *
     * @return array<string, mixed>
     */
    public function getWidgets(): array
    {
        return [
            'extensions' => [
                'icon' => 'puzzle-piece',
                'widget' => 'PhpDebugBar.Widgets.VariableListWidget',
                'map' => 'extensions.dispatches',
                'default' => '{}',
            ],
            'extensions:badge' => [
                'map' => 'extensions.total_dispatches',
                'default' => 0,
            ],
        ];
    }
}
