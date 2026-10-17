<?php

namespace MehrabHossain\LogLens\Services\Apache;

class ApacheRateCalculator
{
    /**
     * Maximum elapsed seconds between readings to consider them consecutive.
     */
    protected float $maxConsecutiveIntervalSeconds;

    public function __construct(float $maxConsecutiveIntervalSeconds = 15.0)
    {
        $this->maxConsecutiveIntervalSeconds = $maxConsecutiveIntervalSeconds;
    }

    /**
     * Calculate differential rates between current and previous readings.
     *
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>|null  $previous
     * @return array{
     *     rps: ?float,
     *     bps: ?float,
     *     elapsed_seconds: ?float,
     *     restart_detected: bool,
     *     counter_reset_detected: bool,
     *     calculation_valid: bool
     * }
     */
    public function calculate(array $current, ?array $previous): array
    {
        if ($previous === null) {
            return [
                'rps' => null,
                'bps' => null,
                'elapsed_seconds' => null,
                'restart_detected' => false,
                'counter_reset_detected' => false,
                'calculation_valid' => false,
            ];
        }

        $currentTime = (float) ($current['timestamp'] ?? microtime(true));
        $previousTime = (float) ($previous['timestamp'] ?? 0.0);
        $elapsed = $currentTime - $previousTime;

        // Ensure elapsed time is reasonable for rate calculation
        if ($elapsed <= 0.0 || $elapsed > $this->maxConsecutiveIntervalSeconds) {
            return [
                'rps' => null,
                'bps' => null,
                'elapsed_seconds' => round($elapsed, 4),
                'restart_detected' => false,
                'counter_reset_detected' => false,
                'calculation_valid' => false,
            ];
        }

        $currentUptime = $current['uptime_seconds'] ?? null;
        $previousUptime = $previous['uptime_seconds'] ?? null;

        $currentAccesses = $current['total_accesses'] ?? null;
        $previousAccesses = $previous['total_accesses'] ?? null;

        $currentBytes = $current['total_bytes'] ?? null;
        $previousBytes = $previous['total_bytes'] ?? null;

        // Detect server restart (uptime drops)
        $restartDetected = false;
        if ($currentUptime !== null && $previousUptime !== null && $currentUptime < $previousUptime) {
            $restartDetected = true;
        }

        // Detect counter reset (accesses or bytes drop)
        $counterReset = false;
        if ($currentAccesses !== null && $previousAccesses !== null && $currentAccesses < $previousAccesses) {
            $counterReset = true;
        }
        if ($currentBytes !== null && $previousBytes !== null && $currentBytes < $previousBytes) {
            $counterReset = true;
        }

        // If server restarted or counter reset, consecutive delta is invalid
        if ($restartDetected || $counterReset) {
            return [
                'rps' => null,
                'bps' => null,
                'elapsed_seconds' => round($elapsed, 4),
                'restart_detected' => $restartDetected,
                'counter_reset_detected' => $counterReset,
                'calculation_valid' => false,
            ];
        }

        // Compute rates if required metrics are present
        $rps = null;
        if ($currentAccesses !== null && $previousAccesses !== null) {
            $deltaAccesses = $currentAccesses - $previousAccesses;
            if ($deltaAccesses >= 0) {
                $rps = round($deltaAccesses / $elapsed, 2);
            }
        }

        $bps = null;
        if ($currentBytes !== null && $previousBytes !== null) {
            $deltaBytes = $currentBytes - $previousBytes;
            if ($deltaBytes >= 0) {
                $bps = round($deltaBytes / $elapsed, 2);
            }
        }

        return [
            'rps' => $rps,
            'bps' => $bps,
            'elapsed_seconds' => round($elapsed, 4),
            'restart_detected' => false,
            'counter_reset_detected' => false,
            'calculation_valid' => ($rps !== null && $bps !== null),
        ];
    }
}
