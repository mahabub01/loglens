<?php

namespace MehrabHossain\LogLens\Tests\Unit;

use MehrabHossain\LogLens\Services\Apache\ApacheRateCalculator;
use MehrabHossain\LogLens\Tests\TestCase;

class ApacheRateCalculatorTest extends TestCase
{
    protected ApacheRateCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new ApacheRateCalculator(maxConsecutiveIntervalSeconds: 15.0);
    }

    public function test_first_reading_returns_null_rates(): void
    {
        $current = [
            'timestamp' => 1000.0,
            'uptime_seconds' => 3600,
            'total_accesses' => 500,
            'total_bytes' => 1048576,
        ];

        $result = $this->calculator->calculate($current, null);

        $this->assertNull($result['rps']);
        $this->assertNull($result['bps']);
        $this->assertFalse($result['calculation_valid']);
        $this->assertFalse($result['restart_detected']);
        $this->assertFalse($result['counter_reset_detected']);
    }

    public function test_calculates_rates_using_actual_elapsed_time(): void
    {
        $previous = [
            'timestamp' => 1000.0,
            'uptime_seconds' => 3600,
            'total_accesses' => 1000,
            'total_bytes' => 2000000,
        ];

        // 1.5 seconds elapsed, 60 requests delta, 150000 bytes delta
        $current = [
            'timestamp' => 1001.5,
            'uptime_seconds' => 3601,
            'total_accesses' => 1060,
            'total_bytes' => 2150000,
        ];

        $result = $this->calculator->calculate($current, $previous);

        $this->assertTrue($result['calculation_valid']);
        $this->assertEquals(1.5, $result['elapsed_seconds']);
        // 60 / 1.5 = 40.0 RPS
        $this->assertEquals(40.0, $result['rps']);
        // 150000 / 1.5 = 100000.0 BPS
        $this->assertEquals(100000.0, $result['bps']);
        $this->assertFalse($result['restart_detected']);
    }

    public function test_detects_server_restart_when_uptime_drops(): void
    {
        $previous = [
            'timestamp' => 1000.0,
            'uptime_seconds' => 86400, // running for 1 day
            'total_accesses' => 50000,
            'total_bytes' => 100000000,
        ];

        // Server was restarted, uptime dropped to 2 seconds
        $current = [
            'timestamp' => 1001.0,
            'uptime_seconds' => 2,
            'total_accesses' => 5,
            'total_bytes' => 10240,
        ];

        $result = $this->calculator->calculate($current, $previous);

        $this->assertTrue($result['restart_detected'], 'Server restart must be detected when uptime drops.');
        $this->assertFalse($result['calculation_valid']);
        $this->assertNull($result['rps'], 'Invalid delta measurements must be null, not 0.');
        $this->assertNull($result['bps'], 'Invalid delta measurements must be null, not 0.');
    }

    public function test_detects_counter_reset_when_accesses_drop(): void
    {
        $previous = [
            'timestamp' => 1000.0,
            'uptime_seconds' => 500,
            'total_accesses' => 10000,
            'total_bytes' => 5000000,
        ];

        // Accesses unexpectedly dropped without uptime drop (e.g. counter wrap)
        $current = [
            'timestamp' => 1001.0,
            'uptime_seconds' => 501,
            'total_accesses' => 10,
            'total_bytes' => 5001000,
        ];

        $result = $this->calculator->calculate($current, $previous);

        $this->assertTrue($result['counter_reset_detected']);
        $this->assertFalse($result['calculation_valid']);
        $this->assertNull($result['rps']);
    }

    public function test_invalidates_rates_when_elapsed_interval_exceeds_threshold(): void
    {
        $previous = [
            'timestamp' => 1000.0,
            'uptime_seconds' => 1000,
            'total_accesses' => 100,
            'total_bytes' => 10000,
        ];

        // 30 seconds later (paused / stale interval)
        $current = [
            'timestamp' => 1030.0,
            'uptime_seconds' => 1030,
            'total_accesses' => 150,
            'total_bytes' => 15000,
        ];

        $result = $this->calculator->calculate($current, $previous);

        $this->assertFalse($result['calculation_valid'], 'Gapped non-consecutive readings should not produce instantaneous rates.');
        $this->assertNull($result['rps']);
        $this->assertNull($result['bps']);
    }
}
