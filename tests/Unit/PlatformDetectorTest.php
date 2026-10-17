<?php

namespace MehrabHossain\LogLens\Tests\Unit;

use MehrabHossain\LogLens\Support\PlatformDetector;
use MehrabHossain\LogLens\Tests\TestCase;

class PlatformDetectorTest extends TestCase
{
    protected PlatformDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();
        PlatformDetector::setMockData(null);
        $this->detector = new PlatformDetector();
    }

    protected function tearDown(): void
    {
        PlatformDetector::setMockData(null);
        parent::tearDown();
    }

    public function test_detects_current_runtime_environment(): void
    {
        $platform = $this->detector->detect();

        $this->assertArrayHasKey('os', $platform);
        $this->assertArrayHasKey('is_linux', $platform);
        $this->assertArrayHasKey('is_windows', $platform);
        $this->assertArrayHasKey('is_docker', $platform);
        $this->assertArrayHasKey('php_version', $platform);
        $this->assertArrayHasKey('hostname', $platform);
        $this->assertSame(PHP_VERSION, $platform['php_version']);
    }

    public function test_respects_injected_mock_platform_data(): void
    {
        PlatformDetector::setMockData([
            'os' => 'Linux',
            'is_linux' => true,
            'is_windows' => false,
            'is_mac' => false,
            'is_docker' => true,
            'cgroup_version' => 2,
            'php_version' => '8.3.0',
            'hostname' => 'k8s-pod-7749',
            'can_sys_load' => true,
            'can_read_proc' => true,
            'disabled_functions' => [],
        ]);

        $platform = $this->detector->detect();

        $this->assertTrue($platform['is_linux']);
        $this->assertTrue($platform['is_docker']);
        $this->assertSame(2, $platform['cgroup_version']);
        $this->assertSame('k8s-pod-7749', $platform['hostname']);
    }
}
