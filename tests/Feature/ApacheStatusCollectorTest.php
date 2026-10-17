<?php

namespace MehrabHossain\LogLens\Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use MehrabHossain\LogLens\Contracts\ApacheCollectorInterface;
use MehrabHossain\LogLens\Contracts\ApacheMetricsStoreInterface;
use MehrabHossain\LogLens\Tests\TestCase;

class ApacheStatusCollectorTest extends TestCase
{
    protected ApacheCollectorInterface $collector;
    protected ApacheMetricsStoreInterface $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enablePackage();
        config(['loglens.apache.enabled' => true]);

        $this->collector = app(ApacheCollectorInterface::class);
        $this->store = app(ApacheMetricsStoreInterface::class);
        $this->store->clear();
    }

    protected function tearDown(): void
    {
        $this->store->clear();
        parent::tearDown();
    }

    public function test_successful_collection_cycle(): void
    {
        $mockOutput = <<<'TXT'
Total Accesses: 2500
Total kBytes: 51200
Uptime: 7200
BusyWorkers: 4
IdleWorkers: 96
Scoreboard: _W_K____R................................................
ServerVersion: Apache/2.4.58 (Ubuntu)
TXT;

        Http::fake([
            'http://127.0.0.1/server-status?auto' => Http::response($mockOutput, 200),
        ]);

        $result = $this->collector->collect();

        $this->assertTrue($result['success']);
        $this->assertSame(4, $result['metrics']['busy_workers']);
        $this->assertSame(96, $result['metrics']['idle_workers']);
        $this->assertSame(2500, $result['metrics']['total_accesses']);
        $this->assertSame(51200 * 1024, $result['metrics']['total_bytes']);

        // Verify stored in shared storage
        $latest = $this->store->getLatest();
        $this->assertNotNull($latest);
        $this->assertSame(4, $latest['busy_workers']);
    }

    public function test_connection_timeout_handling(): void
    {
        Http::fake([
            '*' => function () {
                throw new ConnectionException('Connection timed out after 1000 milliseconds');
            },
        ]);

        $result = $this->collector->collect();

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Unable to connect to Apache status endpoint', $result['error']);

        // Verify error was recorded in the shared store
        $this->assertNotNull($this->store->getLastError());
        $this->assertStringContainsString('Connection error', $this->store->getLastError());
    }

    public function test_connection_refused_or_server_error_handling(): void
    {
        Http::fake([
            '*' => Http::response('Internal Server Error', 500),
        ]);

        $result = $this->collector->collect();

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('HTTP request failed with status 500', $this->store->getLastError());
    }

    public function test_invalid_status_response_handling(): void
    {
        // Web server returned HTML error page
        Http::fake([
            '*' => Http::response('<!DOCTYPE html><html><body>Error</body></html>', 200),
        ]);

        $result = $this->collector->collect();

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Received HTML instead of machine-readable', $result['error']);
    }

    public function test_ssrf_and_arbitrary_url_restrictions(): void
    {
        // 1. Disallowed external host
        config(['loglens.apache.status_url' => 'http://169.254.169.254/latest/meta-data']);
        $result = $this->collector->collect();
        $this->assertFalse($result['success']);
        $this->assertTrue($result['security_violation'] ?? false);
        $this->assertStringContainsString('not in the allowed Apache status hosts list', $result['error']);

        // 2. Prohibited URL scheme (ftp://)
        config(['loglens.apache.status_url' => 'ftp://127.0.0.1/server-status?auto']);
        $resultScheme = $this->collector->collect();
        $this->assertFalse($resultScheme['success']);
        $this->assertStringContainsString('Unsupported URL scheme', $resultScheme['error']);

        // 3. Embedded credentials in URL
        config(['loglens.apache.status_url' => 'http://admin:secret@127.0.0.1/server-status?auto']);
        $resultCreds = $this->collector->collect();
        $this->assertFalse($resultCreds['success']);
        $this->assertStringContainsString('Embedded user credentials in status URL are prohibited', $resultCreds['error']);
    }

    public function test_multiple_dashboard_visitors_do_not_poll_apache(): void
    {
        $this->authorizeAccess();

        // Seed an initial cached snapshot into the shared store
        $this->store->record([
            'timestamp' => microtime(true),
            'busy_workers' => 2,
            'idle_workers' => 98,
            'rps' => 5.0,
        ]);

        // Fake HTTP to track any outbound HTTP calls
        Http::fake();

        // Simulate 5 visitors loading the dashboard page and 5 API polling calls
        for ($i = 0; $i < 5; $i++) {
            $this->get('/loglens/apache')->assertStatus(200);
            $this->get('/loglens/api/metrics/apache')->assertStatus(200);
        }

        // Verify that ZERO outbound HTTP requests were made to Apache!
        Http::assertNothingSent();
    }

    public function test_shared_collector_behavior_and_consecutive_rate_calculation(): void
    {
        $firstReading = <<<'TXT'
Total Accesses: 1000
Total kBytes: 20480
Uptime: 5000
BusyWorkers: 2
IdleWorkers: 98
Scoreboard: _W_K....................................................
ServerVersion: Apache/2.4.58
TXT;

        $secondReading = <<<'TXT'
Total Accesses: 1050
Total kBytes: 21504
Uptime: 5001
BusyWorkers: 4
IdleWorkers: 96
Scoreboard: _WWK....................................................
ServerVersion: Apache/2.4.58
TXT;

        Http::fakeSequence()
            ->push($firstReading, 200)
            ->push($secondReading, 200);

        // First collection cycle
        $res1 = $this->collector->collect();
        $this->assertTrue($res1['success']);
        $this->assertNull($res1['metrics']['rps'], 'First reading cannot calculate rate without baseline.');

        usleep(15000); // 15ms simulated interval

        // Second collection cycle
        $res2 = $this->collector->collect();
        $this->assertTrue($res2['success']);
        $this->assertTrue($res2['metrics']['calculation_valid']);
        $this->assertNotNull($res2['metrics']['rps']);
        $this->assertGreaterThan(0, $res2['metrics']['rps']);
        $this->assertNotNull($res2['metrics']['bps']);
    }
}
