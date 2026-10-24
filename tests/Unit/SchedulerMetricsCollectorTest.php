<?php

namespace MehrabHossain\LogLens\Tests\Unit;

use Exception;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Events\ScheduledTaskStarting;
use MehrabHossain\LogLens\Contracts\SharedMetricsStorageInterface;
use MehrabHossain\LogLens\Services\SchedulerMetricsCollector;
use MehrabHossain\LogLens\Tests\TestCase;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

class SchedulerMetricsCollectorTest extends TestCase
{
    protected SchedulerMetricsCollector $collector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enablePackage();
        config([
            'loglens.scheduler.enabled' => true,
            'loglens.scheduler.max_samples' => 10,
        ]);

        $this->collector = new SchedulerMetricsCollector();
    }

    protected function createMockTask(string $command = 'php artisan backup:run', string $expression = '0 0 * * *', ?string $desc = 'Daily Backup'): \Illuminate\Console\Scheduling\Event
    {
        $mutex = new \Illuminate\Console\Scheduling\CacheEventMutex(app('cache'));
        $event = new \Illuminate\Console\Scheduling\Event($mutex, $command);
        $event->cron($expression);
        $event->description = $desc;

        return $event;
    }

    public function test_handles_task_starting_and_finished_events(): void
    {
        $task = $this->createMockTask('php artisan loglens:clean', '*/15 * * * *');

        $this->collector->handleTaskStarting(new ScheduledTaskStarting($task));
        usleep(5000); // 5ms
        $this->collector->handleTaskFinished(new ScheduledTaskFinished($task, 0.05));

        $summary = $this->collector->getSummary();

        $this->assertSame(1, $summary['total_runs']);
        $this->assertSame(1, $summary['successful_runs']);
        $this->assertSame(0, $summary['failed_runs']);
        $this->assertSame(0, $summary['skipped_runs']);
        $this->assertNotNull($summary['last_run_at']);
        $this->assertCount(1, $summary['recent_tasks']);

        $recent = $summary['recent_tasks'][0];
        $this->assertSame('php artisan loglens:clean', $recent['command']);
        $this->assertSame('*/15 * * * *', $recent['expression']);
        $this->assertSame('success', $recent['status']);
        $this->assertFalse($recent['overlap_warning']);
    }

    public function test_handles_task_failed_event(): void
    {
        $task = $this->createMockTask('php artisan report:generate', '0 8 * * 1');
        $exception = new RuntimeException('Report database deadlocked');

        $this->collector->handleTaskStarting(new ScheduledTaskStarting($task));
        $this->collector->handleTaskFailed(new ScheduledTaskFailed($task, $exception));

        $summary = $this->collector->getSummary();

        $this->assertSame(1, $summary['total_runs']);
        $this->assertSame(0, $summary['successful_runs']);
        $this->assertSame(1, $summary['failed_runs']);
        $this->assertCount(1, $summary['recent_tasks']);

        $recent = $summary['recent_tasks'][0];
        $this->assertSame('failed', $recent['status']);
        $this->assertSame('Report database deadlocked', $recent['error']);
    }

    public function test_handles_task_skipped_and_flags_overlap_warning(): void
    {
        $task = $this->createMockTask('php artisan sync:heavy', '* * * * *');

        $this->collector->handleTaskSkipped(new ScheduledTaskSkipped($task));

        $summary = $this->collector->getSummary();

        $this->assertSame(0, $summary['total_runs']); // Skipped tasks did not run
        $this->assertSame(1, $summary['skipped_runs']);
        $this->assertCount(1, $summary['recent_tasks']);

        $recent = $summary['recent_tasks'][0];
        $this->assertSame('skipped', $recent['status']);
        $this->assertTrue($recent['overlap_warning'], 'Skipped scheduled tasks must be flagged with overlap warnings.');
        $this->assertStringContainsString('withoutOverlapping', $recent['error']);
    }

    public function test_maintains_bounded_history_buffer(): void
    {
        config(['loglens.scheduler.max_samples' => 3]);
        $collector = new SchedulerMetricsCollector();

        for ($i = 1; $i <= 5; $i++) {
            $task = $this->createMockTask("php artisan task:{$i}");
            $collector->handleTaskStarting(new ScheduledTaskStarting($task));
            $collector->handleTaskFinished(new ScheduledTaskFinished($task, 0.01));
        }

        $summary = $collector->getSummary();
        $this->assertSame(5, $summary['total_runs']);
        $this->assertCount(3, $summary['recent_tasks']);

        // Most recent should be task 5
        $this->assertSame('php artisan task:5', $summary['recent_tasks'][0]['command']);
    }

    public function test_reset_clears_tracked_metrics(): void
    {
        $task = $this->createMockTask();
        $this->collector->handleTaskFinished(new ScheduledTaskFinished($task, 0.01));

        $this->assertSame(1, $this->collector->getSummary()['total_runs']);

        $this->collector->reset();

        $summary = $this->collector->getSummary();
        $this->assertSame(0, $summary['total_runs']);
        $this->assertNull($summary['last_run_at']);
        $this->assertEmpty($summary['recent_tasks']);
    }

    public function test_fresh_collector_reads_metrics_recorded_by_another_process(): void
    {
        // Simulate the `schedule:run` console process recording events
        $task = $this->createMockTask('php artisan reports:daily', '0 6 * * *');
        $this->collector->handleTaskStarting(new ScheduledTaskStarting($task));
        usleep(5000); // 5ms
        $this->collector->handleTaskFinished(new ScheduledTaskFinished($task, 0.05));

        $failing = $this->createMockTask('php artisan sync:crm', '*/5 * * * *');
        $this->collector->handleTaskStarting(new ScheduledTaskStarting($failing));
        $this->collector->handleTaskFailed(new ScheduledTaskFailed($failing, new RuntimeException('CRM offline')));

        $this->collector->handleTaskSkipped(new ScheduledTaskSkipped($this->createMockTask('php artisan sync:heavy')));

        // Simulate the dashboard web request with a brand-new collector instance
        $dashboard = new SchedulerMetricsCollector();
        $summary = $dashboard->getSummary();

        $this->assertSame(2, $summary['total_runs']);
        $this->assertSame(1, $summary['successful_runs']);
        $this->assertSame(1, $summary['failed_runs']);
        $this->assertSame(1, $summary['skipped_runs']);
        $this->assertGreaterThan(0, $summary['avg_duration_ms']);
        $this->assertNotNull($summary['last_run_at']);
        $this->assertTrue($summary['is_alive']);
        $this->assertCount(3, $summary['recent_tasks']);
        $this->assertSame('skipped', $summary['recent_tasks'][0]['status']);
        $this->assertSame('failed', $summary['recent_tasks'][1]['status']);
        $this->assertSame('php artisan reports:daily', $summary['recent_tasks'][2]['command']);
    }

    public function test_reset_clears_metrics_for_other_instances(): void
    {
        $this->collector->handleTaskFinished(new ScheduledTaskFinished($this->createMockTask(), 0.01));

        (new SchedulerMetricsCollector())->reset();

        $summary = (new SchedulerMetricsCollector())->getSummary();
        $this->assertSame(0, $summary['total_runs']);
        $this->assertFalse($summary['is_alive']);
        $this->assertEmpty($summary['recent_tasks']);
    }

    public function test_store_failures_never_break_scheduled_tasks(): void
    {
        $collector = new SchedulerMetricsCollector(new FailingMetricsStore());
        $task = $this->createMockTask();

        $collector->handleTaskStarting(new ScheduledTaskStarting($task));
        $collector->handleTaskFinished(new ScheduledTaskFinished($task, 0.01));
        $collector->handleTaskSkipped(new ScheduledTaskSkipped($task));
        $collector->reset();

        $summary = $collector->getSummary();
        $this->assertSame(0, $summary['total_runs']);
        $this->assertNull($summary['last_run_at']);
        $this->assertFalse($summary['is_alive']);
        $this->assertSame([], $summary['recent_tasks']);
    }

    public function test_schedule_run_command_marks_scheduler_running_without_any_task(): void
    {
        $this->assertSame('off', (new SchedulerMetricsCollector())->getSummary()['status']);

        // Cron starts `schedule:run` but no task is due this minute
        $this->collector->handleCommandStarting($this->commandStarting('schedule:run'));

        $summary = (new SchedulerMetricsCollector())->getSummary();
        $this->assertSame('running', $summary['status']);
        $this->assertTrue($summary['is_alive']);
        $this->assertNotNull($summary['last_heartbeat_at']);
        $this->assertSame(0, $summary['total_runs']);
        $this->assertNull($summary['last_run_at']);
    }

    public function test_other_artisan_commands_do_not_record_scheduler_heartbeat(): void
    {
        $this->collector->handleCommandStarting($this->commandStarting('migrate'));
        $this->collector->handleCommandStarting($this->commandStarting('queue:work'));

        $this->assertSame('off', $this->collector->getSummary()['status']);
    }

    public function test_scheduler_is_off_once_heartbeat_leaves_window(): void
    {
        config(['loglens.scheduler.heartbeat_window_seconds' => 120]);

        // Last schedule:run was 5 minutes ago (cron stopped)
        app(SharedMetricsStorageInterface::class)->set('heartbeat:scheduler', microtime(true) - 300, 600);

        $summary = (new SchedulerMetricsCollector())->getSummary();
        $this->assertSame('off', $summary['status']);
        $this->assertFalse($summary['is_alive']);
        $this->assertNull($summary['last_heartbeat_at']);
    }

    protected function commandStarting(string $command): CommandStarting
    {
        return new CommandStarting($command, new ArrayInput([]), new NullOutput());
    }

    public function test_disabled_collector_ignores_events(): void
    {
        config(['loglens.scheduler.enabled' => false]);

        $task = $this->createMockTask();
        $this->collector->handleTaskStarting(new ScheduledTaskStarting($task));
        $this->collector->handleTaskFinished(new ScheduledTaskFinished($task, 0.01));

        $summary = $this->collector->getSummary();
        $this->assertSame(0, $summary['total_runs']);
    }
}
