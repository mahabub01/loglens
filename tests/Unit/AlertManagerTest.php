<?php

namespace MehrabHossain\LogLens\Tests\Unit;

use MehrabHossain\LogLens\Services\AlertManager;
use MehrabHossain\LogLens\Tests\TestCase;

class AlertManagerTest extends TestCase
{
    protected AlertManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enablePackage();
        config([
            'loglens.alerts.enabled' => true,
            'loglens.alerts.cooldown_seconds' => 300,
            'loglens.alerts.thresholds' => [
                'cpu_percent' => 85,
                'memory_percent' => 90,
                'disk_percent' => 90,
                'slow_request_percent' => 10,
                'http_error_percent' => 5,
                'queue_failed_jobs' => 5,
            ],
        ]);
        $this->manager = new AlertManager();
    }

    public function test_triggers_high_cpu_alert(): void
    {
        $context = [
            'system' => [
                'cpu' => ['utilization_percent' => 92.5],
            ],
        ];

        $alerts = $this->manager->evaluate($context);

        $this->assertCount(1, $alerts);
        $this->assertSame('high_cpu', $alerts[0]['key']);
        $this->assertSame('critical', $alerts[0]['severity']);
        $this->assertEquals(92.5, $alerts[0]['current_value']);
    }

    public function test_triggers_high_memory_and_low_disk_alerts(): void
    {
        $context = [
            'system' => [
                'memory' => ['used_percent' => 94.0],
                'disk' => ['used_percent' => 95.5],
            ],
        ];

        $alerts = $this->manager->evaluate($context);

        $this->assertCount(2, $alerts);
        $keys = array_column($alerts, 'key');
        $this->assertContains('high_memory', $keys);
        $this->assertContains('low_disk_space', $keys);
    }

    public function test_triggers_http_error_and_slow_request_alerts(): void
    {
        $context = [
            'http' => [
                'total_requests' => 100,
                'status_distribution' => ['5xx' => 8], // 8% > 5%
                'slow_request_count' => 15, // 15% > 10%
            ],
        ];

        $alerts = $this->manager->evaluate($context);

        $this->assertCount(2, $alerts);
        $keys = array_column($alerts, 'key');
        $this->assertContains('elevated_http_errors', $keys);
        $this->assertContains('high_slow_requests', $keys);
    }

    public function test_triggers_queue_failure_threshold_alert(): void
    {
        $context = [
            'queue' => [
                'failed_count' => 7, // 7 >= 5
            ],
        ];

        $alerts = $this->manager->evaluate($context);

        $this->assertCount(1, $alerts);
        $this->assertSame('queue_failures', $alerts[0]['key']);
        $this->assertSame('critical', $alerts[0]['severity']);
    }

    public function test_alert_cooldown_and_deduplication(): void
    {
        $context = [
            'system' => [
                'cpu' => ['utilization_percent' => 95.0],
            ],
        ];

        // First evaluation: new alert, not in cooldown
        $alerts1 = $this->manager->evaluate($context);
        $this->assertTrue($alerts1[0]['is_new']);
        $this->assertFalse($alerts1[0]['in_cooldown']);

        // Second immediate evaluation: duplicate suppressed under cooldown
        $alerts2 = $this->manager->evaluate($context);
        $this->assertFalse($alerts2[0]['is_new']);
        $this->assertTrue($alerts2[0]['in_cooldown'], 'Repeated alert must be flagged in cooldown to prevent notification spam.');
    }

    public function test_disabled_alerts_returns_empty(): void
    {
        config(['loglens.alerts.enabled' => false]);

        $context = [
            'system' => ['cpu' => ['utilization_percent' => 99.0]],
        ];

        $alerts = $this->manager->evaluate($context);
        $this->assertEmpty($alerts);
    }
}
