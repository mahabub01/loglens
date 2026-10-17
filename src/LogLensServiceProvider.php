<?php

namespace MehrabHossain\LogLens;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use MehrabHossain\LogLens\Contracts\AuthorizerInterface;
use MehrabHossain\LogLens\Http\Middleware\AuthorizeLogLens;
use MehrabHossain\LogLens\Http\Middleware\AuthorizeLogLensDestructive;
use MehrabHossain\LogLens\Http\Middleware\EnsureLogLensIsEnabled;
use MehrabHossain\LogLens\Support\Authorizer;

class LogLensServiceProvider extends ServiceProvider
{
    /**
     * Register any package services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/loglens.php',
            'loglens'
        );

        $this->app->singleton(AuthorizerInterface::class, Authorizer::class);
        $this->app->singleton(Contracts\LogFileManagerInterface::class, Services\LogFileManager::class);
        $this->app->singleton(Contracts\LogParserInterface::class, Services\MonologParser::class);
        $this->app->singleton(Contracts\LogReaderInterface::class, Services\BoundedFileReader::class);
        $this->app->singleton(Services\ErrorFingerprinter::class, Services\ErrorFingerprinter::class);
        $this->app->singleton(Contracts\LogAnalyzerInterface::class, Services\LogAnalyzer::class);
        $this->app->singleton(Contracts\LogCleanupInterface::class, Services\LogCleanupService::class);

        // Phase 3: Zero-Extra-Query Metrics & Instrumentation
        $this->app->singleton(Contracts\MetricsStoreInterface::class, Services\MemoryMetricsStore::class);
        $this->app->singleton(Support\QueryFingerprinter::class, Support\QueryFingerprinter::class);
        $this->app->singleton(Support\CorrelationIdManager::class, Support\CorrelationIdManager::class);
        $this->app->singleton(Support\RequestContext::class, Support\RequestContext::class);
        $this->app->singleton(Contracts\DatabaseMetricsCollectorInterface::class, Services\DatabaseMetricsCollector::class);
        $this->app->singleton(Contracts\HttpMetricsCollectorInterface::class, Services\HttpMetricsCollector::class);

        // Phase 4: Apache mod_status Live Monitoring
        $this->app->singleton(Contracts\ApacheMetricsStoreInterface::class, function ($app) {
            return new Services\Apache\CacheApacheMetricsStore();
        });
        $this->app->singleton(Services\Apache\ApacheModStatusParser::class, Services\Apache\ApacheModStatusParser::class);
        $this->app->singleton(Services\Apache\ApacheRateCalculator::class, Services\Apache\ApacheRateCalculator::class);
        $this->app->singleton(Contracts\ApacheCollectorInterface::class, Services\Apache\ApacheStatusCollector::class);

        // Phase 5: Server Health, Queue, Scheduler, and Alert Monitoring
        $this->app->singleton(Support\PlatformDetector::class, Support\PlatformDetector::class);
        $this->app->singleton(Contracts\SystemMetricsCollectorInterface::class, Services\SystemMetricsCollector::class);
        $this->app->singleton(Contracts\QueueMetricsCollectorInterface::class, Services\QueueMetricsCollector::class);
        $this->app->singleton(Contracts\SchedulerMetricsCollectorInterface::class, Services\SchedulerMetricsCollector::class);
        $this->app->singleton(Contracts\AlertManagerInterface::class, Services\AlertManager::class);
        $this->app->singleton(Contracts\SharedMetricsStorageInterface::class, function ($app) {
            $driver = config('loglens.metrics.driver', 'memory');
            if ($driver === 'redis') {
                return new Services\Storage\RedisMetricsStore();
            }

            return new Services\Storage\CacheMetricsStore();
        });

        $this->app->singleton('loglens', function ($app) {
            return new LogLens();
        });

        $this->registerMiddleware();
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        $this->registerViews();
        $this->registerRoutes();
        $this->registerDatabaseObserver();
        $this->registerQueueObserver();
        $this->registerSchedulerObserver();
        $this->registerCommands();
        $this->registerPublishing();
    }

    /**
     * Register passive queue event listener.
     */
    protected function registerQueueObserver(): void
    {
        $this->app[Contracts\QueueMetricsCollectorInterface::class]->register();
    }

    /**
     * Register passive scheduler event listener.
     */
    protected function registerSchedulerObserver(): void
    {
        $this->app[Contracts\SchedulerMetricsCollectorInterface::class]->register();
    }

    /**
     * Register package artisan commands.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\ApacheCollectCommand::class,
            ]);
        }
    }

    /**
     * Register passive database query event listener.
     */
    protected function registerDatabaseObserver(): void
    {
        $this->app[Contracts\DatabaseMetricsCollectorInterface::class]->register();
    }

    /**
     * Register the package routes.
     */
    protected function registerRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
    }

    /**
     * Register the package views.
     */
    protected function registerViews(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'loglens');
    }

    /**
     * Register the package middleware aliases.
     */
    protected function registerMiddleware(): void
    {
        /** @var Router $router */
        $router = $this->app['router'];

        $router->aliasMiddleware('loglens.enabled', EnsureLogLensIsEnabled::class);
        $router->aliasMiddleware('loglens.auth', AuthorizeLogLens::class);
        $router->aliasMiddleware('loglens.destructive', AuthorizeLogLensDestructive::class);
        $router->aliasMiddleware('loglens.http', Http\Middleware\LogLensHttpMonitoringMiddleware::class);
    }

    /**
     * Register the package's publishable resources.
     */
    protected function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/loglens.php' => config_path('loglens.php'),
            ], 'loglens-config');

            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/loglens'),
            ], 'loglens-views');
        }
    }
}
