<?php

use Illuminate\Support\Facades\Route;
use MehrabHossain\LogLens\Http\Controllers\DashboardController;
use MehrabHossain\LogLens\Http\Controllers\DestructiveActionController;
use MehrabHossain\LogLens\Http\Controllers\ErrorAnalysisController;
use MehrabHossain\LogLens\Http\Controllers\LiveTailController;
use MehrabHossain\LogLens\Http\Controllers\LogCleanupController;
use MehrabHossain\LogLens\Http\Controllers\LogViewerController;
use MehrabHossain\LogLens\Http\Middleware\AuthorizeLogLensDestructive;

Route::group([
    'prefix' => config('loglens.path', 'loglens'),
    'domain' => config('loglens.domain', null),
    'middleware' => config('loglens.middleware', ['web']),
    'as' => 'loglens.',
], function () {
    // Main Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Phase 2: Core Log Analyzer & Management
    Route::get('/logs', [LogViewerController::class, 'index'])->name('logs.index');
    Route::get('/logs/{file}/download', [LogViewerController::class, 'download'])->name('logs.download');
    Route::get('/errors', [ErrorAnalysisController::class, 'index'])->name('errors.index');

    // Phase 2 API: Live Tail
    Route::get('/api/logs/tail', [LiveTailController::class, 'tail'])->name('api.logs.tail');

    // Phase 2 API: Safe Cleanup (Preview is read-only, Execute & Clear require Destructive Authorization)
    Route::post('/api/cleanup/preview', [LogCleanupController::class, 'preview'])->name('api.cleanup.preview');
    Route::post('/api/cleanup/execute', [LogCleanupController::class, 'executeRetention'])
        ->middleware(AuthorizeLogLensDestructive::class)
        ->name('api.cleanup.execute');
    Route::post('/api/logs/clear', [LogCleanupController::class, 'clearFile'])
        ->middleware(AuthorizeLogLensDestructive::class)
        ->name('api.logs.clear');

    // Phase 3: Zero-Extra-Query Database & HTTP Monitoring
    Route::get('/http', [MehrabHossain\LogLens\Http\Controllers\HttpMonitoringController::class, 'index'])->name('http.index');
    Route::get('/database', [MehrabHossain\LogLens\Http\Controllers\DatabaseMonitoringController::class, 'index'])->name('database.index');
    Route::get('/api/metrics/http', [MehrabHossain\LogLens\Http\Controllers\HttpMonitoringController::class, 'api'])->name('api.metrics.http');
    Route::get('/api/metrics/database', [MehrabHossain\LogLens\Http\Controllers\DatabaseMonitoringController::class, 'api'])->name('api.metrics.database');

    // Phase 4: Apache mod_status Live Monitoring
    Route::get('/apache', [MehrabHossain\LogLens\Http\Controllers\ApacheMonitoringController::class, 'index'])->name('apache.index');
    Route::get('/api/metrics/apache', [MehrabHossain\LogLens\Http\Controllers\ApacheMonitoringController::class, 'api'])->name('api.metrics.apache');
    // Phase 5: Server Health, Queue, Scheduler & Alert Monitoring
    Route::get('/server-health', [MehrabHossain\LogLens\Http\Controllers\SystemHealthController::class, 'index'])->name('server.index');
    Route::get('/api/metrics/system', [MehrabHossain\LogLens\Http\Controllers\SystemHealthController::class, 'api'])->name('api.metrics.system');

    Route::get('/queue', [MehrabHossain\LogLens\Http\Controllers\QueueMonitoringController::class, 'index'])->name('queue.index');
    Route::get('/api/metrics/queue', [MehrabHossain\LogLens\Http\Controllers\QueueMonitoringController::class, 'api'])->name('api.metrics.queue');

    Route::get('/scheduler', [MehrabHossain\LogLens\Http\Controllers\SchedulerMonitoringController::class, 'index'])->name('scheduler.index');
    Route::get('/api/metrics/scheduler', [MehrabHossain\LogLens\Http\Controllers\SchedulerMonitoringController::class, 'api'])->name('api.metrics.scheduler');

    Route::get('/api/alerts', [MehrabHossain\LogLens\Http\Controllers\AlertsController::class, 'api'])->name('api.alerts');

    Route::get('/settings', [DashboardController::class, 'index'])->name('settings.index');

    // Phase 1 Destructive Action Convention Endpoint
    Route::post('/api/actions/execute', [DestructiveActionController::class, 'execute'])
        ->middleware(AuthorizeLogLensDestructive::class)
        ->name('actions.execute');
});
