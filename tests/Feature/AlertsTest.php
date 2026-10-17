<?php

namespace MehrabHossain\LogLens\Tests\Feature;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessing;
use MehrabHossain\LogLens\Contracts\QueueMetricsCollectorInterface;
use MehrabHossain\LogLens\Tests\TestCase;
use RuntimeException;

class AlertsTest extends TestCase
{
    protected function createMockJob(string $id = 'job-alert-test')
    {
        return new class($id) {
            public function __construct(protected string $id) {}
            public function getJobId(): string { return $this->id; }
            public function resolveName(): string { return 'App\\Jobs\\AlertFailingJob'; }
            public function getQueue(): string { return 'default'; }
        };
    }

    public function test_unauthorized_user_cannot_access_alerts_api(): void
    {
        $this->enablePackage();
        config(['loglens.auth.allow_local_by_default' => false]);

        $response = $this->get('/loglens/api/alerts');
        $response->assertStatus(403);
    }

    public function test_authorized_user_can_access_alerts_api(): void
    {
        $this->enablePackage();
        $this->authorizeAccess();

        $response = $this->get('/loglens/api/alerts');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'alerts',
            'timestamp',
        ]);
        $response->assertJson(['success' => true]);
    }

    public function test_alerts_api_detects_queue_failure_threshold(): void
    {
        $this->enablePackage();
        $this->authorizeAccess();

        config([
            'loglens.queue.enabled' => true,
            'loglens.alerts.enabled' => true,
            'loglens.alerts.thresholds.queue_failed_jobs' => 3,
        ]);

        /** @var QueueMetricsCollectorInterface $queue */
        $queue = app(QueueMetricsCollectorInterface::class);

        // Fail 4 jobs
        for ($i = 1; $i <= 4; $i++) {
            $job = $this->createMockJob("fail-{$i}");
            $queue->handleJobProcessing(new JobProcessing('redis', $job));
            $queue->handleJobFailed(new JobFailed('redis', $job, new RuntimeException("Error {$i}")));
        }

        $response = $this->get('/loglens/api/alerts');

        $response->assertStatus(200);
        $alerts = $response->json('alerts');

        $this->assertNotEmpty($alerts);
        $keys = array_column($alerts, 'key');
        $this->assertContains('queue_failures', $keys);

        $queueAlert = collect($alerts)->firstWhere('key', 'queue_failures');
        $this->assertSame('critical', $queueAlert['severity']);
        $this->assertSame(4, $queueAlert['current_value']);
        $this->assertSame(3, $queueAlert['threshold']);
    }

    public function test_alerts_api_returns_empty_when_alerts_disabled(): void
    {
        $this->enablePackage();
        $this->authorizeAccess();

        config(['loglens.alerts.enabled' => false]);

        $response = $this->get('/loglens/api/alerts');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('alerts'));
    }
}
