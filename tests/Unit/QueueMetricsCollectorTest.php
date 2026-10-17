<?php

namespace MehrabHossain\LogLens\Tests\Unit;

use Exception;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use MehrabHossain\LogLens\Services\QueueMetricsCollector;
use MehrabHossain\LogLens\Tests\TestCase;
use RuntimeException;

class QueueMetricsCollectorTest extends TestCase
{
    protected QueueMetricsCollector $collector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enablePackage();
        config([
            'loglens.queue.enabled' => true,
            'loglens.queue.max_samples' => 20,
            'loglens.queue.long_running_threshold_ms' => 100, // 100ms for fast tests
        ]);

        $this->collector = new QueueMetricsCollector();
    }

    protected function createMockJob(string $id = 'job-123', string $name = 'App\\Jobs\\SendEmailJob', string $queue = 'emails')
    {
        return new class($id, $name, $queue) {
            public function __construct(
                protected string $id,
                protected string $name,
                protected string $queue
            ) {}

            public function getJobId(): string
            {
                return $this->id;
            }

            public function resolveName(): string
            {
                return $this->name;
            }

            public function getQueue(): string
            {
                return $this->queue;
            }
        };
    }

    public function test_handles_job_processing_and_processed_events(): void
    {
        $job = $this->createMockJob('job-1', 'App\\Jobs\\ProcessInvoice', 'invoices');

        $this->collector->handleJobProcessing(new JobProcessing('redis', $job));
        usleep(5000); // 5ms
        $this->collector->handleJobProcessed(new JobProcessed('redis', $job));

        $summary = $this->collector->getSummary();

        $this->assertSame(1, $summary['processed_count']);
        $this->assertSame(0, $summary['failed_count']);
        $this->assertGreaterThan(0, $summary['total_duration_ms']);
        $this->assertCount(1, $summary['recent_processed']);

        $recent = $summary['recent_processed'][0];
        $this->assertSame('job-1', $recent['id']);
        $this->assertSame('App\\Jobs\\ProcessInvoice', $recent['name']);
        $this->assertSame('invoices', $recent['queue']);
        $this->assertSame('redis', $recent['connection']);
    }

    public function test_handles_job_failed_event(): void
    {
        $job = $this->createMockJob('job-err', 'App\\Jobs\\SyncInventory', 'default');
        $exception = new RuntimeException('Connection timed out during sync');

        $this->collector->handleJobProcessing(new JobProcessing('database', $job));
        $this->collector->handleJobFailed(new JobFailed('database', $job, $exception));

        $summary = $this->collector->getSummary();

        $this->assertSame(0, $summary['processed_count']);
        $this->assertSame(1, $summary['failed_count']);
        $this->assertEquals(100.0, $summary['failure_rate_percent']);
        $this->assertCount(1, $summary['recent_failed']);

        $failed = $summary['recent_failed'][0];
        $this->assertSame('job-err', $failed['id']);
        $this->assertSame(RuntimeException::class, $failed['exception_class']);
        $this->assertSame('Connection timed out during sync', $failed['exception_message']);
    }

    public function test_identifies_long_running_jobs(): void
    {
        $job = $this->createMockJob('job-long', 'App\\Jobs\\HeavyReport', 'reports');

        $this->collector->handleJobProcessing(new JobProcessing('sync', $job));
        usleep(120000); // 120ms > 100ms threshold
        $this->collector->handleJobProcessed(new JobProcessed('sync', $job));

        $summary = $this->collector->getSummary();

        $this->assertSame(1, $summary['long_running_count']);
        $this->assertTrue($summary['recent_processed'][0]['is_long_running']);
    }

    public function test_backlog_polling_disabled_by_default(): void
    {
        config(['loglens.queue.poll_backlog' => false]);

        $summary = $this->collector->getSummary();
        $this->assertNull($summary['backlog'], 'Backlog polling must be disabled by default to uphold strict zero-query guarantee.');
    }

    public function test_backlog_polling_when_enabled(): void
    {
        config([
            'loglens.queue.poll_backlog' => true,
            'loglens.queue.monitored_queues' => ['default', 'high-priority'],
        ]);

        $summary = $this->collector->getSummary();
        $this->assertIsArray($summary['backlog']);
        $this->assertArrayHasKey('default', $summary['backlog']);
        $this->assertArrayHasKey('high-priority', $summary['backlog']);
    }

    public function test_reset_clears_tracked_metrics(): void
    {
        $job = $this->createMockJob('job-reset', 'App\\Jobs\\Dummy', 'default');
        $this->collector->handleJobProcessed(new JobProcessed('sync', $job));

        $this->assertSame(1, $this->collector->getSummary()['processed_count']);

        $this->collector->reset();

        $summary = $this->collector->getSummary();
        $this->assertSame(0, $summary['processed_count']);
        $this->assertSame(0, $summary['failed_count']);
        $this->assertEmpty($summary['recent_processed']);
    }

    public function test_disabled_collector_ignores_events(): void
    {
        config(['loglens.queue.enabled' => false]);

        $job = $this->createMockJob('job-ignored', 'App\\Jobs\\Ignored', 'default');
        $this->collector->handleJobProcessing(new JobProcessing('sync', $job));
        $this->collector->handleJobProcessed(new JobProcessed('sync', $job));

        $summary = $this->collector->getSummary();
        $this->assertSame(0, $summary['processed_count']);
    }
}
