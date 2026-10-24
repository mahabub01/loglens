<?php

namespace MehrabHossain\LogLens\Tests\Feature;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\DB;
use MehrabHossain\LogLens\Contracts\QueueMetricsCollectorInterface;
use MehrabHossain\LogLens\Tests\TestCase;
use RuntimeException;

class QueueMonitoringTest extends TestCase
{
    protected function createMockJob(string $id = 'job-order-1', string $name = 'App\\Jobs\\ProcessOrder', string $queue = 'orders')
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

    protected function setUp(): void
    {
        parent::setUp();
        config(['loglens.queue.enabled' => true]);
    }

    public function test_unauthorized_user_cannot_access_queue_monitoring(): void
    {
        $this->enablePackage();
        config(['loglens.auth.allow_local_by_default' => false]);

        $response = $this->get('/loglens/queue');
        $response->assertStatus(403);

        $apiResponse = $this->get('/loglens/api/metrics/queue');
        $apiResponse->assertStatus(403);
    }

    public function test_authorized_user_can_view_queue_dashboard(): void
    {
        $this->enablePackage();
        $this->authorizeAccess();

        $response = $this->get('/loglens/queue');

        $response->assertStatus(200);
        $response->assertSee('Queue &amp; Background Job Instrumentation', false);
        $response->assertSee('Processed Jobs');
        $response->assertSee('Failed Jobs');
        $response->assertSee('Failure Rate');
        $response->assertSee('Avg Execution');
        $response->assertSee('Long Running');
    }

    public function test_queue_metrics_api_returns_json_structure(): void
    {
        $this->enablePackage();
        $this->authorizeAccess();

        $response = $this->get('/loglens/api/metrics/queue');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'summary' => [
                'enabled',
                'processed_count',
                'failed_count',
                'active_jobs_count',
                'avg_duration_ms',
                'total_duration_ms',
                'long_running_count',
                'long_running_threshold_ms',
                'failure_rate_percent',
                'backlog',
                'recent_failed',
                'recent_processed',
            ],
            'timestamp',
        ]);
        $response->assertJson(['success' => true]);
    }

    public function test_zero_extra_database_queries_on_queue_dashboard_refresh(): void
    {
        $this->enablePackage();
        $this->authorizeAccess();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->get('/loglens/queue');

        $response->assertStatus(200);
        $queries = DB::getQueryLog();
        $this->assertCount(0, $queries, 'Viewing queue metrics must execute ZERO database queries in strict mode.');
    }

    public function test_queue_dashboard_displays_processed_and_failed_jobs(): void
    {
        $this->enablePackage();
        $this->authorizeAccess();

        /** @var QueueMetricsCollectorInterface $collector */
        $collector = app(QueueMetricsCollectorInterface::class);

        // Process a successful job
        $jobSuccess = $this->createMockJob('job-success-123', 'App\\Jobs\\SendWelcomeEmail', 'default');
        $collector->handleJobProcessing(new JobProcessing('redis', $jobSuccess));
        $collector->handleJobProcessed(new JobProcessed('redis', $jobSuccess));

        // Process a failed job
        $jobFailed = $this->createMockJob('job-fail-456', 'App\\Jobs\\ChargeCreditCard', 'payments');
        $collector->handleJobProcessing(new JobProcessing('redis', $jobFailed));
        $collector->handleJobFailed(new JobFailed('redis', $jobFailed, new RuntimeException('Card expired')));

        $response = $this->get('/loglens/queue');

        $response->assertStatus(200);
        $response->assertSee('App\\Jobs\\SendWelcomeEmail');
        $response->assertSee('App\\Jobs\\ChargeCreditCard');
        $response->assertSee('Card expired');
        $response->assertSee('payments');
    }

    public function test_dashboard_shows_worker_status_from_idle_loop_heartbeat(): void
    {
        $this->enablePackage();
        $this->authorizeAccess();

        $this->get('/loglens/queue')->assertSee('Worker Off');

        // Dispatched through the real event bus, as `queue:work` does while polling an empty queue
        event(new Looping('database', 'default'));

        $this->get('/loglens/queue')
            ->assertSee('Worker Running')
            ->assertDontSee('Worker Off');
    }
}
