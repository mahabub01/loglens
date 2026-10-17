<?php

namespace MehrabHossain\LogLens\Tests\Unit;

use Exception;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Events\ScheduledTaskStarting;
use MehrabHossain\LogLens\Services\SchedulerMetricsCollector;
use MehrabHossain\LogLens\Tests\TestCase;
use RuntimeException;

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
