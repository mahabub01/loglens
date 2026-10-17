<?php

namespace MehrabHossain\LogLens\Services;

use MehrabHossain\LogLens\Contracts\AlertManagerInterface;
use MehrabHossain\LogLens\LogLens;

class AlertManager implements AlertManagerInterface
{
    /**
     * @var array<string, array<string, mixed>> Tracks active alerts keyed by alert identifier
     */
    protected array $activeAlerts = [];

    /**
     * @var array<string, float> Last notified timestamp per alert key for cooldown/deduplication
     */
    protected array $lastNotifiedAt = [];

    protected int $cooldownSeconds;

    public function __construct()
    {
        $this->cooldownSeconds = (int) config('loglens.alerts.cooldown_seconds', 300);
    }

    /**
     * Evaluate metrics against configured alert thresholds.
     */
    public function evaluate(array $metricsContext): array
    {
        if (! $this->isEnabled()) {
            return [];
        }

        $now = microtime(true);
        $thresholds = config('loglens.alerts.thresholds', [
            'cpu_percent' => 85,
            'memory_percent' => 90,
            'disk_percent' => 90,
            'slow_request_percent' => 10,
            'http_error_percent' => 5,
            'queue_failed_jobs' => 5,
        ]);

        $evaluatedAlerts = [];

        // 1. High CPU Utilization
        $cpuPercent = $metricsContext['system']['cpu']['utilization_percent'] ?? null;
        if ($cpuPercent !== null && $cpuPercent >= ($thresholds['cpu_percent'] ?? 85)) {
            $evaluatedAlerts[] = $this->buildAlert(
                'high_cpu',
                'High CPU Utilization',
                "CPU utilization reached {$cpuPercent}%, exceeding threshold of {$thresholds['cpu_percent']}%.",
                'critical',
                'cpu_utilization_percent',
                $cpuPercent,
                $thresholds['cpu_percent']
            );
        }

        // 2. High Memory Utilization
        $memPercent = $metricsContext['system']['memory']['used_percent'] ?? null;
        if ($memPercent !== null && $memPercent >= ($thresholds['memory_percent'] ?? 90)) {
            $evaluatedAlerts[] = $this->buildAlert(
                'high_memory',
                'High Memory Utilization',
                "Memory utilization reached {$memPercent}%, exceeding threshold of {$thresholds['memory_percent']}%.",
                'critical',
                'memory_used_percent',
                $memPercent,
                $thresholds['memory_percent']
            );
        }

        // 3. Low Disk Space
        $diskPercent = $metricsContext['system']['disk']['used_percent'] ?? null;
        if ($diskPercent !== null && $diskPercent >= ($thresholds['disk_percent'] ?? 90)) {
            $evaluatedAlerts[] = $this->buildAlert(
                'low_disk_space',
                'Low Disk Space',
                "Disk utilization reached {$diskPercent}%, exceeding threshold of {$thresholds['disk_percent']}%.",
                'critical',
                'disk_used_percent',
                $diskPercent,
                $thresholds['disk_percent']
            );
        }

        // 4. Elevated HTTP Error Rate
        $http = $metricsContext['http'] ?? null;
        if ($http && ($http['total_requests'] ?? 0) >= 10) {
            $dist = $http['status_distribution'] ?? [];
            $errs = ($dist['5xx'] ?? 0);
            $errRate = round(($errs / $http['total_requests']) * 100, 1);
            if ($errRate >= ($thresholds['http_error_percent'] ?? 5)) {
                $evaluatedAlerts[] = $this->buildAlert(
                    'elevated_http_errors',
                    'Elevated 5xx HTTP Error Rate',
                    "HTTP 5xx server error rate reached {$errRate}% ({$errs} errors), exceeding threshold of {$thresholds['http_error_percent']}%.",
                    'critical',
                    'http_5xx_rate_percent',
                    $errRate,
                    $thresholds['http_error_percent']
                );
            }

            // 5. High Slow Request Rate
            $slowCount = $http['slow_request_count'] ?? 0;
            $slowRate = round(($slowCount / $http['total_requests']) * 100, 1);
            if ($slowRate >= ($thresholds['slow_request_percent'] ?? 10)) {
                $evaluatedAlerts[] = $this->buildAlert(
                    'high_slow_requests',
                    'High Slow Request Rate',
                    "Slow HTTP requests reached {$slowRate}% ({$slowCount} requests), exceeding threshold of {$thresholds['slow_request_percent']}%.",
                    'warning',
                    'slow_request_rate_percent',
                    $slowRate,
                    $thresholds['slow_request_percent']
                );
            }
        }

        // 6. Queue Failure Threshold
        $queue = $metricsContext['queue'] ?? null;
        if ($queue && ($queue['failed_count'] ?? 0) >= ($thresholds['queue_failed_jobs'] ?? 5)) {
            $evaluatedAlerts[] = $this->buildAlert(
                'queue_failures',
                'Queue Failure Threshold Exceeded',
                "Queue reported {$queue['failed_count']} failed jobs, exceeding threshold of {$thresholds['queue_failed_jobs']}.",
                'critical',
                'queue_failed_count',
                $queue['failed_count'],
                $thresholds['queue_failed_jobs']
            );
        }

        // Update active alerts state
        $this->activeAlerts = [];
        foreach ($evaluatedAlerts as $alert) {
            $this->activeAlerts[$alert['key']] = $alert;
        }

        return array_values($this->activeAlerts);
    }

    /**
     * Get all active alerts currently triggered.
     */
    public function getActiveAlerts(): array
    {
        return array_values($this->activeAlerts);
    }

    /**
     * Clear all active alerts.
     */
    public function clearAlerts(): void
    {
        $this->activeAlerts = [];
        $this->lastNotifiedAt = [];
    }

    /**
     * Determine if alert evaluation is enabled.
     */
    public function isEnabled(): bool
    {
        return LogLens::isEnabled() && (bool) config('loglens.alerts.enabled', true);
    }

    /**
     * Construct an alert payload with deduplication and cooldown evaluation.
     */
    protected function buildAlert(
        string $key,
        string $title,
        string $message,
        string $severity,
        string $metric,
        mixed $currentValue,
        mixed $threshold
    ): array {
        $now = microtime(true);
        $lastNotified = $this->lastNotifiedAt[$key] ?? 0.0;
        $inCooldown = ($now - $lastNotified) < $this->cooldownSeconds;

        if (! $inCooldown) {
            $this->lastNotifiedAt[$key] = $now;
        }

        return [
            'key' => $key,
            'title' => $title,
            'message' => $message,
            'severity' => $severity,
            'metric' => $metric,
            'current_value' => $currentValue,
            'threshold' => $threshold,
            'triggered_at' => $now,
            'in_cooldown' => $inCooldown,
            'is_new' => ! $inCooldown,
        ];
    }
}
