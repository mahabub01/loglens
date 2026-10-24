<?php

namespace MehrabHossain\LogLens\Tests\Unit;

use Exception;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\Looping;
use Illuminate\Queue\Events\WorkerStopping;
use Illuminate\Support\Facades\Event;
use MehrabHossain\LogLens\Contracts\SharedMetricsStorageInterface;
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

    public function test_fresh_collector_reads_metrics_recorded_by_a_worker(): void
    {
        // Simulate the long-running `queue:work` process recording events
        $job = $this->createMockJob('job-a', 'App\\Jobs\\ProcessInvoice', 'invoices');
        $this->collector->handleJobProcessing(new JobProcessing('database', $job));
        usleep(5000); // 5ms
        $this->collector->handleJobProcessed(new JobProcessed('database', $job));

        $failed = $this->createMockJob('job-b', 'App\\Jobs\\SyncInventory', 'default');
        $this->collector->handleJobProcessing(new JobProcessing('database', $failed));
        $this->collector->handleJobFailed(new JobFailed('database', $failed, new RuntimeException('Timeout')));

        // Simulate the dashboard web request with a brand-new collector instance
        $dashboard = new QueueMetricsCollector();
        $summary = $dashboard->getSummary();

        $this->assertSame(1, $summary['processed_count']);
        $this->assertSame(1, $summary['failed_count']);
        $this->assertEquals(50.0, $summary['failure_rate_percent']);
        $this->assertGreaterThan(0, $summary['total_duration_ms']);
        $this->assertGreaterThan(0, $summary['avg_duration_ms']);
        $this->assertCount(1, $summary['recent_processed']);
        $this->assertSame('App\\Jobs\\ProcessInvoice', $summary['recent_processed'][0]['name']);
        $this->assertCount(1, $summary['recent_failed']);
        $this->assertSame('Timeout', $summary['recent_failed'][0]['exception_message']);
    }

    public function test_counts_from_multiple_workers_are_aggregated(): void
    {
        $workerA = new QueueMetricsCollector();
        $workerB = new QueueMetricsCollector();

        $workerA->handleJobProcessed(new JobProcessed('redis', $this->createMockJob('job-1')));
        $workerB->handleJobProcessed(new JobProcessed('redis', $this->createMockJob('job-2')));
        $workerB->handleJobProcessed(new JobProcessed('redis', $this->createMockJob('job-3')));

        $summary = (new QueueMetricsCollector())->getSummary();
        $this->assertSame(3, $summary['processed_count']);
        $this->assertSame('job-3', $summary['recent_processed'][0]['id']);
    }

    public function test_store_failures_never_break_job_processing(): void
    {
        $collector = new QueueMetricsCollector(new FailingMetricsStore());
        $job = $this->createMockJob('job-x', 'App\\Jobs\\Dummy', 'default');

        $collector->handleJobProcessing(new JobProcessing('sync', $job));
        $collector->handleJobProcessed(new JobProcessed('sync', $job));
        $collector->handleJobFailed(new JobFailed('sync', $job, new RuntimeException('boom')));
        $collector->reset();

        $summary = $collector->getSummary();
        $this->assertSame(0, $summary['processed_count']);
        $this->assertSame(0, $summary['failed_count']);
        $this->assertFalse($summary['is_alive']);
        $this->assertSame([], $summary['recent_processed']);
    }

    public function test_idle_worker_loop_marks_worker_running(): void
    {
        $this->assertSame('off', (new QueueMetricsCollector())->getSummary()['status']);

        // Empty queue: the worker only polls, no job events fire
        $this->collector->handleWorkerLooping(new Looping('database', 'default'));

        $summary = (new QueueMetricsCollector())->getSummary();
        $this->assertSame('running', $summary['status']);
        $this->assertTrue($summary['is_alive']);
        $this->assertNotNull($summary['last_heartbeat_at']);
        $this->assertSame(0, $summary['processed_count']);
    }

    public function test_idle_loop_heartbeat_is_throttled_per_worker(): void
    {
        config(['loglens.queue.heartbeat_interval_seconds' => 10]);
        $worker = new QueueMetricsCollector(workerId: 'web01:100');
        $store = app(SharedMetricsStorageInterface::class);

        $worker->handleWorkerLooping(new Looping('database', 'default'));
        $first = $store->get('queue:worker:web01:100')['last_seen'];

        // Polls every ~3s must not rewrite the worker state inside the 10s interval
        usleep(2000);
        $worker->handleWorkerLooping(new Looping('database', 'default'));
        $worker->handleWorkerLooping(new Looping('database', 'default'));

        $this->assertSame($first, $store->get('queue:worker:web01:100')['last_seen']);
    }

    public function test_lists_each_worker_with_its_state(): void
    {
        $workerA = new QueueMetricsCollector(workerId: 'web01:100');
        $workerB = new QueueMetricsCollector(workerId: 'web01:101');

        $workerA->handleWorkerLooping(new Looping('database', 'default'));
        $workerB->handleWorkerLooping(new Looping('redis', 'emails,default'));

        $job = $this->createMockJob('job-1', 'App\\Jobs\\SendInvoiceMail', 'emails');
        $workerA->handleJobProcessing(new JobProcessing('database', $job));
        $workerA->handleJobProcessed(new JobProcessed('database', $job));
        $workerB->handleJobProcessing(new JobProcessing('redis', $this->createMockJob('job-2', 'App\\Jobs\\GenerateReport', 'emails')));

        $summary = (new QueueMetricsCollector())->getSummary();

        $this->assertSame(2, $summary['workers_online']);
        $this->assertSame('running', $summary['status']);
        $this->assertSame(1, $summary['active_jobs_count']);

        [$a, $b] = $summary['workers'];
        $this->assertSame('web01:100', $a['id']);
        $this->assertSame('idle', $a['status']);
        $this->assertSame('default', $a['queue']);
        $this->assertSame('database', $a['connection']);
        $this->assertSame(1, $a['processed']);
        $this->assertNull($a['current_job']);
        $this->assertGreaterThan(0, $a['memory_mb']);

        $this->assertSame('web01:101', $b['id']);
        $this->assertSame('busy', $b['status']);
        $this->assertSame('emails,default', $b['queue']);
        $this->assertSame('App\\Jobs\\GenerateReport', $b['current_job']);
        $this->assertNotNull($b['busy_since']);
    }

    public function test_long_running_job_shows_busy_instead_of_off(): void
    {
        $store = app(SharedMetricsStorageInterface::class);
        $worker = new QueueMetricsCollector(workerId: 'web01:100');
        $job = $this->createMockJob('job-long', 'App\\Jobs\\HeavyExport', 'reports');

        $worker->handleWorkerLooping(new Looping('database', 'reports'));
        $worker->handleJobProcessing(new JobProcessing('database', $job));

        // Job has been running for 5 minutes: the worker stopped looping, its heartbeat went stale
        $state = $store->get('queue:worker:web01:100');
        $state['last_seen'] = microtime(true) - 300;
        $state['busy_since'] = microtime(true) - 300;
        $store->set('queue:worker:web01:100', $state, 900);

        $summary = (new QueueMetricsCollector())->getSummary();
        $this->assertSame('busy', $summary['status']);
        $this->assertTrue($summary['is_alive']);
        $this->assertNotNull($summary['busy_since']);
        $this->assertSame('App\\Jobs\\HeavyExport', $summary['workers'][0]['current_job']);

        $worker->handleJobProcessed(new JobProcessed('database', $job));

        $summary = (new QueueMetricsCollector())->getSummary();
        $this->assertSame('running', $summary['status']);
        $this->assertNull($summary['busy_since']);
        $this->assertSame('idle', $summary['workers'][0]['status']);
    }

    public function test_worker_is_off_when_heartbeat_is_stale_and_not_busy(): void
    {
        $store = app(SharedMetricsStorageInterface::class);
        (new QueueMetricsCollector(workerId: 'web01:100'))->handleWorkerLooping(new Looping('database', 'default'));

        // Worker was killed 2 minutes ago without a clean shutdown
        $state = $store->get('queue:worker:web01:100');
        $state['last_seen'] = microtime(true) - 120;
        $store->set('queue:worker:web01:100', $state, 600);

        $summary = (new QueueMetricsCollector())->getSummary();
        $this->assertSame('off', $summary['status']);
        $this->assertFalse($summary['is_alive']);
        $this->assertSame(0, $summary['workers_online']);
        $this->assertNull($summary['last_heartbeat_at']);
    }

    public function test_stopping_worker_is_removed_immediately_without_affecting_others(): void
    {
        $workerA = new QueueMetricsCollector(workerId: 'web01:100');
        $workerB = new QueueMetricsCollector(workerId: 'web01:101');
        $workerA->handleWorkerLooping(new Looping('database', 'default'));
        $workerB->handleWorkerLooping(new Looping('database', 'default'));

        $workerA->handleWorkerStopping(new WorkerStopping(0));

        $summary = (new QueueMetricsCollector())->getSummary();
        $this->assertSame(1, $summary['workers_online']);
        $this->assertSame('web01:101', $summary['workers'][0]['id']);
        $this->assertSame('running', $summary['status']);
    }

    public function test_jobs_run_without_worker_loop_do_not_register_a_worker(): void
    {
        // QUEUE_CONNECTION=sync runs jobs inline in a web request: no queue:work exists
        $job = $this->createMockJob('job-sync', 'App\\Jobs\\Inline', 'default');
        $this->collector->handleJobProcessing(new JobProcessing('sync', $job));
        $this->collector->handleJobProcessed(new JobProcessed('sync', $job));

        $summary = (new QueueMetricsCollector())->getSummary();
        $this->assertSame(1, $summary['processed_count']);
        $this->assertSame('off', $summary['status']);
        $this->assertSame(0, $summary['workers_online']);
    }

    public function test_reports_expected_worker_count(): void
    {
        config(['loglens.queue.expected_workers' => '3']);
        (new QueueMetricsCollector(workerId: 'web01:100'))->handleWorkerLooping(new Looping('database', 'default'));

        $summary = (new QueueMetricsCollector())->getSummary();
        $this->assertSame(3, $summary['expected_workers']);
        $this->assertSame(1, $summary['workers_online']);

        config(['loglens.queue.expected_workers' => null]);
        $this->assertNull((new QueueMetricsCollector())->getSummary()['expected_workers']);
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
