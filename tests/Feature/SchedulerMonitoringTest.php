<?php

namespace MehrabHossain\LogLens\Tests\Feature;

use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\CacheEventMutex;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Support\Facades\DB;
use MehrabHossain\LogLens\Contracts\SchedulerMetricsCollectorInterface;
use MehrabHossain\LogLens\Tests\TestCase;
use RuntimeException;

class SchedulerMonitoringTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['loglens.scheduler.enabled' => true]);
    }

    protected function createMockTask(string $command = 'php artisan inspire', string $expression = '* * * * *'): ScheduledEvent
    {
        $mutex = new CacheEventMutex(app('cache'));
        $event = new ScheduledEvent($mutex, $command);
        $event->cron($expression);

        return $event;
    }

    public function test_unauthorized_user_cannot_access_scheduler_monitoring(): void
    {
        $this->enablePackage();
        config(['loglens.auth.allow_local_by_default' => false]);

        $response = $this->get('/loglens/scheduler');
        $response->assertStatus(403);

        $apiResponse = $this->get('/loglens/api/metrics/scheduler');
        $apiResponse->assertStatus(403);
    }

    public function test_authorized_user_can_view_scheduler_dashboard(): void
    {
        $this->enablePackage();
        $this->authorizeAccess();

        $response = $this->get('/loglens/scheduler');

        $response->assertStatus(200);
        $response->assertSee('Laravel Scheduler Observation');
        $response->assertSee('Total Executions');
        $response->assertSee('Successes');
        $response->assertSee('Failures');
        $response->assertSee('Overlap Skips');
        $response->assertSee('Avg Duration');
        $response->assertSee('Last Activity');
    }

    public function test_scheduler_metrics_api_returns_json_structure(): void
    {
        $this->enablePackage();
        $this->authorizeAccess();

        $response = $this->get('/loglens/api/metrics/scheduler');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'summary' => [
                'enabled',
                'total_runs',
                'successful_runs',
                'failed_runs',
                'skipped_runs',
                'avg_duration_ms',
                'last_run_at',
                'active_tasks_count',
                'recent_tasks',
            ],
            'timestamp',
        ]);
        $response->assertJson(['success' => true]);
    }

    public function test_zero_extra_database_queries_on_scheduler_dashboard_refresh(): void
    {
        $this->enablePackage();
        $this->authorizeAccess();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->get('/loglens/scheduler');

        $response->assertStatus(200);
        $queries = DB::getQueryLog();
        $this->assertCount(0, $queries, 'Viewing scheduler metrics must execute ZERO database queries in strict mode.');
    }

    public function test_scheduler_dashboard_displays_recorded_tasks_and_overlap_warnings(): void
    {
        $this->enablePackage();
        $this->authorizeAccess();

        /** @var SchedulerMetricsCollectorInterface $collector */
        $collector = app(SchedulerMetricsCollectorInterface::class);

        // 1. Finished task
        $taskSuccess = $this->createMockTask('php artisan telemetry:prune', '0 3 * * *');
        $collector->handleTaskStarting(new ScheduledTaskStarting($taskSuccess));
        $collector->handleTaskFinished(new ScheduledTaskFinished($taskSuccess, 0.05));

        // 2. Failed task
        $taskFailed = $this->createMockTask('php artisan reports:export', '0 5 * * *');
        $collector->handleTaskStarting(new ScheduledTaskStarting($taskFailed));
        $collector->handleTaskFailed(new ScheduledTaskFailed($taskFailed, new RuntimeException('Disk full during export')));

        // 3. Overlap skipped task
        $taskSkipped = $this->createMockTask('php artisan sync:catalogs', '*/5 * * * *');
        $collector->handleTaskSkipped(new ScheduledTaskSkipped($taskSkipped));

        $response = $this->get('/loglens/scheduler');

        $response->assertStatus(200);
        $response->assertSee('php artisan telemetry:prune');
        $response->assertSee('php artisan reports:export');
        $response->assertSee('Disk full during export');
        $response->assertSee('php artisan sync:catalogs');
        $response->assertSee('Skipped (Overlap)');
    }
}
