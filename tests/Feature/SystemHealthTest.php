<?php

namespace MehrabHossain\LogLens\Tests\Feature;

use MehrabHossain\LogLens\Contracts\SystemMetricsCollectorInterface;
use MehrabHossain\LogLens\Services\SystemMetricsCollector;
use MehrabHossain\LogLens\Support\PlatformDetector;
use MehrabHossain\LogLens\Tests\TestCase;

class SystemHealthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        PlatformDetector::setMockData(null);
        SystemMetricsCollector::setFileReader(null);
    }

    protected function tearDown(): void
    {
        PlatformDetector::setMockData(null);
        SystemMetricsCollector::setFileReader(null);
        parent::tearDown();
    }

    public function test_unauthorized_user_cannot_access_server_health(): void
    {
        $this->enablePackage();
        config(['loglens.auth.allow_local_by_default' => false]);

        $response = $this->get('/loglens/server-health');
        $response->assertStatus(403);

        $apiResponse = $this->get('/loglens/api/metrics/system');
        $apiResponse->assertStatus(403);
    }

    public function test_authorized_user_can_view_server_health_dashboard(): void
    {
        $this->enablePackage();
        $this->authorizeAccess();

        $response = $this->get('/loglens/server-health');

        $response->assertStatus(200);
        $response->assertSee('Operating System & Host Resource Monitor');
        $response->assertSee('CPU Utilization');
        $response->assertSee('System Memory');
        $response->assertSee('Swap Space');
        $response->assertSee('Disk Space');
        $response->assertSee('App Logs Size');
        $response->assertSee('Runtime & Hosting Environment');
    }

    public function test_system_metrics_api_returns_json(): void
    {
        $this->enablePackage();
        $this->authorizeAccess();

        $response = $this->get('/loglens/api/metrics/system');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'metrics' => [
                'timestamp',
                'platform',
                'load_average',
                'cpu',
                'memory',
                'swap',
                'disk',
                'log_directory',
                'network',
            ],
            'alerts',
            'timestamp',
        ]);
        $response->assertJson(['success' => true]);
    }

    public function test_renders_active_alerts_when_thresholds_exceeded(): void
    {
        $this->enablePackage();
        $this->authorizeAccess();

        // Inject high memory consumption
        PlatformDetector::setMockData([
            'os' => 'Linux',
            'is_linux' => true,
            'is_windows' => false,
            'is_mac' => false,
            'is_docker' => false,
            'cgroup_version' => null,
            'php_version' => '8.3.0',
            'hostname' => 'prod-high-load',
            'can_sys_load' => false,
            'can_read_proc' => true,
            'disabled_functions' => [],
        ]);

        $meminfoMock = <<<'TXT'
MemTotal:       10000000 kB
MemFree:          100000 kB
MemAvailable:     400000 kB
TXT;

        SystemMetricsCollector::setFileReader(function (string $path) use ($meminfoMock) {
            if ($path === '/proc/meminfo') {
                return $meminfoMock;
            }
            return null;
        });

        $response = $this->get('/loglens/server-health');

        $response->assertStatus(200);
        $response->assertSee('High Memory Utilization');
        $response->assertSee('CRITICAL');
    }
}
