<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions;

use Esegments\LaravelExtensions\CircuitBreaker\CircuitBreaker;
use Esegments\LaravelExtensions\Console\CacheCommand;
use Esegments\LaravelExtensions\Console\ClearCommand;
use Esegments\LaravelExtensions\Console\IdeHelperCommand;
use Esegments\LaravelExtensions\Console\InspectCommand;
use Esegments\LaravelExtensions\Console\ListCommand;
use Esegments\LaravelExtensions\Console\StatsCommand;
use Esegments\LaravelExtensions\Console\TestCommand;
use Esegments\LaravelExtensions\Discovery\AttributeDiscovery;
use Esegments\LaravelExtensions\Logging\ExtensionLogger;
use Esegments\LaravelExtensions\Pipeline\ExtensionPipeline;
use Esegments\LaravelExtensions\Profiling\ExecutionProfiler;
use Esegments\LaravelExtensions\Registration\WildcardMatcher;
use Esegments\LaravelExtensions\Scoping\ScopedRegistry;
use Esegments\LaravelExtensions\Strategies\FirstResultStrategy;
use Esegments\LaravelExtensions\Strategies\MergeResultsStrategy;
use Esegments\LaravelExtensions\Strategies\ReduceResultsStrategy;
use Esegments\LaravelExtensions\Support\IdeHelperGenerator;
use Esegments\LaravelExtensions\Validation\SignatureValidator;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Cache;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ExtensionServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('extensions')
            ->hasConfigFile()
            ->hasCommands([
                ListCommand::class,
                InspectCommand::class,
                TestCommand::class,
                CacheCommand::class,
                ClearCommand::class,
                StatsCommand::class,
                IdeHelperCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        // Core components
        $this->app->singleton(HandlerRegistry::class, function (): HandlerRegistry {
            return new HandlerRegistry;
        });

        $this->app->singleton(AttributeDiscovery::class, function (Container $app): AttributeDiscovery {
            return new AttributeDiscovery(
                $app->make(HandlerRegistry::class)
            );
        });

        $this->app->singleton(ExtensionLogger::class, function (): ExtensionLogger {
            return new ExtensionLogger(config('extensions.log_channel'));
        });

        // Circuit breaker
        $this->app->singleton(CircuitBreaker::class, function (Container $app): ?CircuitBreaker {
            $config = config('extensions.circuit_breaker', []);

            if (! ($config['enabled'] ?? true)) {
                return null;
            }

            return new CircuitBreaker(
                cache: $app->make(CacheRepository::class),
                threshold: $config['threshold'] ?? 5,
                timeout: $config['timeout'] ?? 60,
                halfOpenMax: $config['half_open_max'] ?? 3,
                enabled: true,
                logChannel: config('extensions.log_channel'),
            );
        });

        // Profiler
        $this->app->singleton(ExecutionProfiler::class, function (): ExecutionProfiler {
            return new ExecutionProfiler(
                enabled: (bool) config('extensions.profiling.enabled', false),
                slowThreshold: (float) config('extensions.profiling.slow_threshold', 100),
                logChannel: config('extensions.profiling.log_channel'),
            );
        });

        // Registration helpers
        $this->app->singleton(WildcardMatcher::class, function (Container $app): WildcardMatcher {
            return new WildcardMatcher($app->make(HandlerRegistry::class));
        });

        $this->app->singleton(ScopedRegistry::class, function (Container $app): ScopedRegistry {
            return new ScopedRegistry($app->make(HandlerRegistry::class));
        });

        // Validation
        $this->app->singleton(SignatureValidator::class, function (): SignatureValidator {
            return new SignatureValidator;
        });

        // IDE Helper
        $this->app->singleton(IdeHelperGenerator::class, function (Container $app): IdeHelperGenerator {
            return new IdeHelperGenerator($app->make(HandlerRegistry::class));
        });

        // Main dispatcher
        $this->app->singleton(ExtensionDispatcher::class, function (Container $app): ExtensionDispatcher {
            // Laravel binds the event dispatcher as 'events' abstract
            $events = $app->bound('events') ? $app->make('events') : null;

            // Get circuit breaker if enabled
            $circuitBreaker = config('extensions.circuit_breaker.enabled', true)
                ? $app->make(CircuitBreaker::class)
                : null;

            return new ExtensionDispatcher(
                container: $app,
                registry: $app->make(HandlerRegistry::class),
                events: $events,
                debug: (bool) config('extensions.debug', false),
                logChannel: config('extensions.log_channel'),
                strictMode: (bool) config('extensions.strict_mode', false),
                circuitBreaker: $circuitBreaker,
            );
        });

        // Strategies
        $this->app->bind(FirstResultStrategy::class, function (): FirstResultStrategy {
            return new FirstResultStrategy;
        });

        $this->app->bind(MergeResultsStrategy::class, function (): MergeResultsStrategy {
            return new MergeResultsStrategy;
        });

        // Convenient aliases
        $this->app->alias(HandlerRegistry::class, 'extensions.registry');
        $this->app->alias(ExtensionDispatcher::class, 'extensions.dispatcher');
        $this->app->alias(AttributeDiscovery::class, 'extensions.discovery');
        $this->app->alias(CircuitBreaker::class, 'extensions.circuit_breaker');
        $this->app->alias(ExecutionProfiler::class, 'extensions.profiler');
        $this->app->alias(WildcardMatcher::class, 'extensions.wildcard');
        $this->app->alias(ScopedRegistry::class, 'extensions.scoped');
        $this->app->alias(SignatureValidator::class, 'extensions.validator');
        $this->app->alias(FirstResultStrategy::class, 'extensions.strategy.first');
        $this->app->alias(MergeResultsStrategy::class, 'extensions.strategy.merge');
    }

    public function packageBooted(): void
    {
        // Auto-discover handlers if enabled
        if (config('extensions.discovery.enabled', false)) {
            $this->discoverHandlers();
        }
    }

    /**
     * Discover and register handlers from configured directories.
     */
    protected function discoverHandlers(): void
    {
        $discovery = $this->app->make(AttributeDiscovery::class);
        $directories = $this->getDiscoveryDirectories();

        if (empty($directories)) {
            return;
        }

        // Check cache if enabled
        $cacheEnabled = config('extensions.discovery.cache', true);
        $cacheKey = config('extensions.discovery.cache_key', 'extensions.discovered_handlers');

        if ($cacheEnabled && Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);
            $this->registerCachedHandlers($cached);

            return;
        }

        // Discover handlers
        $count = $discovery->discoverAndRegister($directories);

        // Cache discovered handlers if enabled
        if ($cacheEnabled && $count > 0) {
            Cache::forever($cacheKey, $discovery->getDiscoveredHandlers());
        }

        // Log discovery if debug enabled
        if (config('extensions.debug', false)) {
            $this->app->make(ExtensionLogger::class)->logDiscovery($count, $directories);
        }
    }

    /**
     * Get discovery directories as absolute paths.
     *
     * @return array<string>
     */
    protected function getDiscoveryDirectories(): array
    {
        $configured = config('extensions.discovery.directories', []);
        $directories = [];

        foreach ($configured as $dir) {
            $path = base_path($dir);
            if (is_dir($path)) {
                $directories[] = $path;
            }
        }

        return $directories;
    }

    /**
     * Register handlers from cached data.
     *
     * @param  array<string, array{extensionPoint: string, handler: string, priority: int}>  $cached
     */
    protected function registerCachedHandlers(array $cached): void
    {
        $registry = $this->app->make(HandlerRegistry::class);

        foreach ($cached as $handler) {
            $registry->register(
                $handler['extensionPoint'],
                $handler['handler'],
                $handler['priority']
            );
        }
    }
}
