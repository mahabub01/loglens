<?php

namespace MehrabHossain\LogLens\Tests\Unit;

use MehrabHossain\LogLens\Services\SystemMetricsCollector;
use MehrabHossain\LogLens\Support\PlatformDetector;
use MehrabHossain\LogLens\Tests\TestCase;

class SystemMetricsCollectorTest extends TestCase
{
    protected SystemMetricsCollector $collector;

    protected function setUp(): void
    {
        parent::setUp();
        PlatformDetector::setMockData(null);
        SystemMetricsCollector::setFileReader(null);
        $this->collector = new SystemMetricsCollector(new PlatformDetector());
    }

    protected function tearDown(): void
    {
        PlatformDetector::setMockData(null);
        SystemMetricsCollector::setFileReader(null);
        parent::tearDown();
    }

    public function test_unsupported_platform_reports_unavailable_metrics_as_null(): void
    {
        PlatformDetector::setMockData([
            'os' => 'Windows',
            'is_linux' => false,
            'is_windows' => true,
            'is_mac' => false,
            'is_docker' => false,
            'cgroup_version' => null,
            'php_version' => '8.3.0',
            'hostname' => 'win-dev-station',
            'can_sys_load' => false,
            'can_read_proc' => false,
            'disabled_functions' => [],
        ]);

        $metrics = $this->collector->collect();

        $this->assertNull($metrics['load_average']);
        $this->assertNull($metrics['cpu']);
        $this->assertNull($metrics['memory']['total_bytes'], 'Missing host metrics must be reported as null, not zero.');
        $this->assertFalse($metrics['memory']['is_available']);
        $this->assertGreaterThan(0, $metrics['memory']['process_usage_bytes']);
        $this->assertFalse($metrics['swap']['is_available']);
        $this->assertNull($metrics['network']);
    }

    public function test_parses_linux_proc_meminfo(): void
    {
        PlatformDetector::setMockData([
            'os' => 'Linux',
            'is_linux' => true,
            'is_windows' => false,
            'is_mac' => false,
            'is_docker' => false,
            'cgroup_version' => null,
            'php_version' => '8.3.0',
            'hostname' => 'prod-web-01',
            'can_sys_load' => false,
            'can_read_proc' => true,
            'disabled_functions' => [],
        ]);

        $meminfoMock = <<<'TXT'
MemTotal:       16384000 kB
MemFree:         2048000 kB
MemAvailable:    8192000 kB
Buffers:          500000 kB
Cached:          1500000 kB
SwapTotal:       4096000 kB
SwapFree:        2048000 kB
TXT;

        SystemMetricsCollector::setFileReader(function (string $path) use ($meminfoMock) {
            if ($path === '/proc/meminfo') {
                return $meminfoMock;
            }
            return null;
        });

        $metrics = $this->collector->collect();

        $mem = $metrics['memory'];
        $this->assertTrue($mem['is_available']);
        $this->assertSame('proc_meminfo', $mem['source']);
        $this->assertSame(16384000 * 1024, $mem['total_bytes']);
        $this->assertSame(8192000 * 1024, $mem['free_bytes']);
        $this->assertEquals(50.0, $mem['used_percent']);

        $swap = $metrics['swap'];
        $this->assertTrue($swap['is_available']);
        $this->assertSame(4096000 * 1024, $swap['total_bytes']);
        $this->assertSame(2048000 * 1024, $swap['free_bytes']);
        $this->assertEquals(50.0, $swap['used_percent']);
    }

    public function test_parses_container_cgroups_v2_memory(): void
    {
        PlatformDetector::setMockData([
            'os' => 'Linux',
            'is_linux' => true,
            'is_windows' => false,
            'is_mac' => false,
            'is_docker' => true,
            'cgroup_version' => 2,
            'php_version' => '8.3.0',
            'hostname' => 'k8s-pod-123',
            'can_sys_load' => false,
            'can_read_proc' => true,
            'disabled_functions' => [],
        ]);

        SystemMetricsCollector::setFileReader(function (string $path) {
            if ($path === '/sys/fs/cgroup/memory.max') {
                return "4294967296\n"; // 4 GB limit
            }
            if ($path === '/sys/fs/cgroup/memory.current') {
                return "2147483648\n"; // 2 GB current
            }
            return null;
        });

        $metrics = $this->collector->collect();
        $mem = $metrics['memory'];

        $this->assertTrue($mem['is_available']);
        $this->assertTrue($mem['is_container']);
        $this->assertSame('cgroups_v2', $mem['source']);
        $this->assertSame(4294967296, $mem['total_bytes']);
        $this->assertSame(2147483648, $mem['used_bytes']);
        $this->assertEquals(50.0, $mem['used_percent']);
    }

    public function test_parses_container_cgroups_v1_memory(): void
    {
        PlatformDetector::setMockData([
            'os' => 'Linux',
            'is_linux' => true,
            'is_windows' => false,
            'is_mac' => false,
            'is_docker' => true,
            'cgroup_version' => 1,
            'php_version' => '8.3.0',
            'hostname' => 'docker-container-456',
            'can_sys_load' => false,
            'can_read_proc' => true,
            'disabled_functions' => [],
        ]);

        SystemMetricsCollector::setFileReader(function (string $path) {
            if ($path === '/sys/fs/cgroup/memory/memory.limit_in_bytes') {
                return "2147483648\n"; // 2 GB limit
            }
            if ($path === '/sys/fs/cgroup/memory/memory.usage_in_bytes') {
                return "1073741824\n"; // 1 GB usage
            }
            return null;
        });

        $metrics = $this->collector->collect();
        $mem = $metrics['memory'];

        $this->assertTrue($mem['is_available']);
        $this->assertSame('cgroups_v1', $mem['source']);
        $this->assertSame(2147483648, $mem['total_bytes']);
        $this->assertSame(1073741824, $mem['used_bytes']);
        $this->assertEquals(50.0, $mem['used_percent']);
    }

    public function test_disk_space_calculation(): void
    {
        $metrics = $this->collector->collect();
        $disk = $metrics['disk'];

        if ($disk !== null) {
            $this->assertArrayHasKey('total_bytes', $disk);
            $this->assertArrayHasKey('free_bytes', $disk);
            $this->assertArrayHasKey('used_bytes', $disk);
            $this->assertArrayHasKey('used_percent', $disk);
            $this->assertGreaterThan(0, $disk['total_bytes']);
            $this->assertEquals($disk['total_bytes'], $disk['used_bytes'] + $disk['free_bytes']);
        } else {
            $this->assertNull($disk);
        }
    }

    public function test_log_directory_size_calculation(): void
    {
        $this->createLogFile('laravel-test-size.log', str_repeat('A', 2048));

        $metrics = $this->collector->collect();
        $logDir = $metrics['log_directory'];

        $this->assertGreaterThanOrEqual(1, $logDir['file_count']);
        $this->assertGreaterThanOrEqual(2048, $logDir['total_bytes']);
    }

    public function test_cpu_utilization_calculation_from_proc_stat(): void
    {
        PlatformDetector::setMockData([
            'os' => 'Linux',
            'is_linux' => true,
            'is_windows' => false,
            'is_mac' => false,
            'is_docker' => false,
            'cgroup_version' => null,
            'php_version' => '8.3.0',
            'hostname' => 'prod-node',
            'can_sys_load' => false,
            'can_read_proc' => true,
            'disabled_functions' => [],
        ]);

        $stat1 = "cpu  100 0 50 850 0 0 0 0\n"; // total: 1000, active: 150
        $stat2 = "cpu  150 0 75 875 0 0 0 0\n"; // total: 1100, active: 225

        $currentStat = $stat1;
        SystemMetricsCollector::setFileReader(function (string $path) use (&$currentStat) {
            if ($path === '/proc/stat') {
                return $currentStat;
            }
            return null;
        });

        // First collect establishes baseline
        $res1 = $this->collector->collect();
        $this->assertNull($res1['cpu']['utilization_percent']);

        // Second collect computes delta: activeDelta = 75, totalDelta = 100 -> 75%
        $currentStat = $stat2;
        $res2 = $this->collector->collect();

        $this->assertEquals(75.0, $res2['cpu']['utilization_percent']);
    }

    public function test_collector_handles_malformed_and_missing_sources_gracefully(): void
    {
        PlatformDetector::setMockData([
            'os' => 'Linux',
            'is_linux' => true,
            'is_windows' => false,
            'is_mac' => false,
            'is_docker' => false,
            'cgroup_version' => null,
            'php_version' => '8.3.0',
            'hostname' => 'prod-error-node',
            'can_sys_load' => false,
            'can_read_proc' => true,
            'disabled_functions' => [],
        ]);

        // Mock returns garbage data or null
        SystemMetricsCollector::setFileReader(function (string $path) {
            if ($path === '/proc/stat') {
                return "corrupted content\nno cpu line here";
            }
            if ($path === '/proc/meminfo') {
                return "NotAValidMeminfoFile: test";
            }
            if ($path === '/proc/net/dev') {
                return "Invalid network dev format";
            }
            return null;
        });

        $metrics = $this->collector->collect();

        $this->assertNull($metrics['cpu']);
        $this->assertFalse($metrics['memory']['is_available']);
        $this->assertNull($metrics['memory']['total_bytes']);
        $this->assertFalse($metrics['swap']['is_available']);
        $this->assertNull($metrics['network']);
    }
}
