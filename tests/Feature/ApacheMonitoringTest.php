<?php

namespace MehrabHossain\LogLens\Tests\Feature;

use MehrabHossain\LogLens\Contracts\ApacheMetricsStoreInterface;
use MehrabHossain\LogLens\Tests\TestCase;

class ApacheMonitoringTest extends TestCase
{
    protected ApacheMetricsStoreInterface $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->store = app(ApacheMetricsStoreInterface::class);
        $this->store->clear();
    }

    protected function tearDown(): void
    {
        $this->store->clear();
        parent::tearDown();
    }

    public function test_unauthorized_user_cannot_access_apache_monitoring(): void
    {
        $this->enablePackage();
        config(['loglens.auth.allow_local_by_default' => false]);

        $response = $this->get('/loglens/apache');
        $response->assertStatus(403);

        $apiResponse = $this->get('/loglens/api/metrics/apache');
        $apiResponse->assertStatus(403);
    }

    public function test_authorized_user_can_view_apache_monitoring_page(): void
    {
        $this->enablePackage();
        $this->authorizeAccess();

        // Seed sample snapshot
        $this->store->record([
            'timestamp' => microtime(true),
            'server_version' => 'Apache/2.4.58 (Ubuntu)',
            'uptime_seconds' => 3600,
            'total_accesses' => 1500,
            'total_bytes' => 1048576,
            'busy_workers' => 2,
            'idle_workers' => 98,
            'rps' => 15.5,
            'bps' => 20480.0,
            'scoreboard_raw' => '_W_R...................',
            'scoreboard_breakdown' => [
                'writing' => 1,
                'reading' => 1,
                'waiting' => 2,
                'open_slot' => 19,
            ],
            'collected_at' => date('Y-m-d H:i:s'),
        ]);

        $response = $this->get('/loglens/apache');

        $response->assertStatus(200);
        $response->assertSee('Apache mod_status Live Monitor');
        $response->assertSee('Requests / Sec');
        $response->assertSee('Throughput');
        $response->assertSee('Busy Workers');
        $response->assertSee('Idle Workers');
        $response->assertSee('Total Accesses');
        $response->assertSee('Worker Scoreboard Distribution');
        $response->assertSee('Apache/2.4.58 (Ubuntu)');
    }

    public function test_apache_metrics_api_returns_json(): void
    {
        $this->enablePackage();
        $this->authorizeAccess();

        $this->store->record([
            'timestamp' => microtime(true),
            'busy_workers' => 5,
            'idle_workers' => 95,
            'rps' => 10.0,
        ]);

        $response = $this->get('/loglens/api/metrics/apache');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'status' => [
                'enabled',
                'state',
                'is_stale',
                'status_url',
                'collection_interval_seconds',
            ],
            'latest' => [
                'busy_workers',
                'idle_workers',
                'rps',
            ],
            'history',
            'timestamp',
        ]);
        $response->assertJson([
            'success' => true,
            'latest' => [
                'busy_workers' => 5,
                'idle_workers' => 95,
            ],
        ]);
    }

    public function test_renders_stale_alert_banner_when_metrics_are_stale(): void
    {
        $this->enablePackage();
        $this->authorizeAccess();
        config(['loglens.apache.enabled' => true]);

        // Record a snapshot 20 seconds in the past
        $this->store->record([
            'timestamp' => microtime(true) - 20,
            'busy_workers' => 1,
            'idle_workers' => 99,
        ]);

        $response = $this->get('/loglens/apache');

        $response->assertStatus(200);
        $response->assertSee('Metrics are Stale');
    }

    public function test_renders_collector_error_alert_when_present(): void
    {
        $this->enablePackage();
        $this->authorizeAccess();

        $this->store->recordError('Connection refused to Apache server-status on port 80.');

        $response = $this->get('/loglens/apache');

        $response->assertStatus(200);
        $response->assertSee('Collector Reported Error:');
        $response->assertSee('Connection refused to Apache server-status on port 80.');
    }
}
