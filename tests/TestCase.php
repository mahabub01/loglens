<?php

namespace MehrabHossain\LogLens\Tests;

use Illuminate\Support\Facades\Gate;
use MehrabHossain\LogLens\Facades\LogLens;
use MehrabHossain\LogLens\LogLensServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected string $tempLogDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Reset static callbacks between tests
        \MehrabHossain\LogLens\LogLens::resetAuth();

        // Create an isolated temporary directory for test log files
        $this->tempLogDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'loglens_test_' . uniqid();
        if (! is_dir($this->tempLogDir)) {
            @mkdir($this->tempLogDir, 0777, true);
        }

        config(['loglens.logs.paths' => [$this->tempLogDir]]);
    }

    protected function tearDown(): void
    {
        \MehrabHossain\LogLens\LogLens::resetAuth();

        if (isset($this->tempLogDir) && is_dir($this->tempLogDir)) {
            $this->deleteDirectoryRecursively($this->tempLogDir);
        }

        parent::tearDown();
    }

    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LogLensServiceProvider::class,
        ];
    }

    /**
     * Get package aliases.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<string, class-string>
     */
    protected function getPackageAliases($app): array
    {
        return [
            'LogLens' => LogLens::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function defineEnvironment($app): void
    {
        // By default, package is disabled
        $app['config']->set('loglens.enabled', false);
        $app['config']->set('app.key', 'base64:6Cu/ozMDviEbPD81754/6rYF6Q4fE7g3f9a76J1u0eQ=');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    /**
     * Helper to enable package in current test.
     */
    protected function enablePackage(): void
    {
        config(['loglens.enabled' => true]);

        if ($this->app->bound(\MehrabHossain\LogLens\Contracts\DatabaseMetricsCollectorInterface::class)) {
            $this->app->make(\MehrabHossain\LogLens\Contracts\DatabaseMetricsCollectorInterface::class)->register();
        }

        if ($this->app->bound(\MehrabHossain\LogLens\Contracts\QueueMetricsCollectorInterface::class)) {
            $this->app->make(\MehrabHossain\LogLens\Contracts\QueueMetricsCollectorInterface::class)->register();
        }

        if ($this->app->bound(\MehrabHossain\LogLens\Contracts\SchedulerMetricsCollectorInterface::class)) {
            $this->app->make(\MehrabHossain\LogLens\Contracts\SchedulerMetricsCollectorInterface::class)->register();
        }
    }

    /**
     * Helper to authorize access via LogLens::auth().
     */
    protected function authorizeAccess(): void
    {
        \MehrabHossain\LogLens\LogLens::auth(function ($request) {
            return true;
        });
    }

    /**
     * Helper to authorize destructive access via LogLens::destructiveAuth().
     */
    protected function authorizeDestructiveAccess(): void
    {
        config(['loglens.cleanup.allow_destructive_actions' => true]);

        \MehrabHossain\LogLens\LogLens::destructiveAuth(function ($request) {
            return true;
        });
    }

    /**
     * Helper to write a test log file to the temporary test log directory.
     */
    protected function createLogFile(string $filename, string $content): string
    {
        $path = $this->tempLogDir . DIRECTORY_SEPARATOR . $filename;
        file_put_contents($path, $content);

        return $path;
    }

    /**
     * Recursively delete a directory and all its files.
     */
    protected function deleteDirectoryRecursively(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if (! $items) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->deleteDirectoryRecursively($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
