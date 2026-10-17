<?php

namespace MehrabHossain\LogLens\Services;

use MehrabHossain\LogLens\Contracts\MetricsStoreInterface;

class MemoryMetricsStore implements MetricsStoreInterface
{
    protected int $maxSamples;

    /**
     * @var array<int, array<string, mixed>>
     */
    protected array $httpMetrics = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    protected array $recentQueries = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    protected array $slowQueries = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    protected array $potentialNPlusOne = [];

    /**
     * @var array<string, int>
     */
    protected array $statusDistribution = [
        '2xx' => 0,
        '3xx' => 0,
        '4xx' => 0,
        '5xx' => 0,
    ];

    /**
     * @var array<string, int>
     */
    protected array $connectionDistribution = [];

    protected int $totalQueries = 0;
    protected float $totalQueryDurationMs = 0.0;
    protected int $slowQueryCount = 0;

    public function __construct(?int $maxSamples = null)
    {
        $this->maxSamples = $maxSamples ?? (int) config('loglens.metrics.max_samples', 500);
    }

    /**
     * Record an individual HTTP request metric.
     */
    public function recordHttp(array $metric): void
    {
        $this->httpMetrics[] = $metric;
        if (count($this->httpMetrics) > $this->maxSamples) {
            array_shift($this->httpMetrics);
        }

        // Tally status code distribution
        $statusCode = (int) ($metric['status_code'] ?? 200);
        $bucket = match (true) {
            $statusCode >= 200 && $statusCode < 300 => '2xx',
            $statusCode >= 300 && $statusCode < 400 => '3xx',
            $statusCode >= 400 && $statusCode < 500 => '4xx',
            $statusCode >= 500 => '5xx',
            default => '2xx',
        };
        $this->statusDistribution[$bucket]++;
    }

    /**
     * Record an individual observed database query event.
     */
    public function recordQuery(array $metric): void
    {
        $this->totalQueries++;
        $duration = (float) ($metric['duration_ms'] ?? 0.0);
        $this->totalQueryDurationMs += $duration;

        $conn = (string) ($metric['connection'] ?? 'default');
        $this->connectionDistribution[$conn] = ($this->connectionDistribution[$conn] ?? 0) + 1;

        $this->recentQueries[] = $metric;
        if (count($this->recentQueries) > $this->maxSamples) {
            array_shift($this->recentQueries);
        }

        if (! empty($metric['is_slow'])) {
            $this->slowQueryCount++;
            $this->slowQueries[] = $metric;
            if (count($this->slowQueries) > $this->maxSamples) {
                array_shift($this->slowQueries);
            }
        }
    }

    /**
     * Record aggregated request-level query execution data, including potential N+1 warnings.
     */
    public function recordRequestSummary(array $summary): void
    {
        if (! empty($summary['n_plus_one_alerts'])) {
            foreach ($summary['n_plus_one_alerts'] as $alert) {
                $this->potentialNPlusOne[] = $alert;
                if (count($this->potentialNPlusOne) > $this->maxSamples) {
                    array_shift($this->potentialNPlusOne);
                }
            }
        }
    }

    /**
     * Get aggregated HTTP metrics summary.
     */
    public function getHttpSummary(): array
    {
        $count = count($this->httpMetrics);
        if ($count === 0) {
            return [
                'total_requests' => 0,
                'avg_response_time_ms' => 0.0,
                'median_response_time_ms' => 0.0,
                'p95_response_time_ms' => 0.0,
                'p99_response_time_ms' => 0.0,
                'slow_request_count' => 0,
                'status_distribution' => $this->statusDistribution,
                'recent_requests' => [],
                'recent_slow_requests' => [],
            ];
        }

        $durations = array_column($this->httpMetrics, 'duration_ms');
        sort($durations);

        $totalDuration = array_sum($durations);
        $avgDuration = round($totalDuration / $count, 2);
        $median = $this->calculatePercentile($durations, 50);
        $p95 = $this->calculatePercentile($durations, 95);
        $p99 = $this->calculatePercentile($durations, 99);

        $slowCount = count(array_filter($this->httpMetrics, fn ($m) => ! empty($m['is_slow'])));

        // Return latest 50 requests in reverse chronological order
        $recent = array_reverse(array_slice($this->httpMetrics, -50));
        $recentSlow = array_values(array_filter($recent, fn ($m) => ! empty($m['is_slow'])));

        return [
            'total_requests' => $count,
            'avg_response_time_ms' => $avgDuration,
            'median_response_time_ms' => $median,
            'p95_response_time_ms' => $p95,
            'p99_response_time_ms' => $p99,
            'slow_request_count' => $slowCount,
            'status_distribution' => $this->statusDistribution,
            'recent_requests' => $recent,
            'recent_slow_requests' => $recentSlow,
        ];
    }

    /**
     * Get aggregated database query metrics summary.
     */
    public function getDatabaseSummary(): array
    {
        $avgDuration = $this->totalQueries > 0
            ? round($this->totalQueryDurationMs / $this->totalQueries, 2)
            : 0.0;

        return [
            'total_queries' => $this->totalQueries,
            'total_duration_ms' => round($this->totalQueryDurationMs, 2),
            'avg_duration_ms' => $avgDuration,
            'slow_query_count' => $this->slowQueryCount,
            'n_plus_one_warnings_count' => count($this->potentialNPlusOne),
            'connection_distribution' => $this->connectionDistribution,
            'slow_queries' => array_reverse(array_slice($this->slowQueries, -50)),
            'potential_n_plus_one' => array_reverse(array_slice($this->potentialNPlusOne, -50)),
        ];
    }

    /**
     * Calculate percentile from a sorted array of numbers.
     *
     * @param  array<int, float>  $sortedValues
     */
    protected function calculatePercentile(array $sortedValues, float $percentile): float
    {
        $count = count($sortedValues);
        if ($count === 0) {
            return 0.0;
        }

        $index = ($percentile / 100) * ($count - 1);
        $floorIndex = (int) floor($index);
        $ceilIndex = (int) ceil($index);

        if ($floorIndex === $ceilIndex) {
            return round($sortedValues[$floorIndex], 2);
        }

        $fraction = $index - $floorIndex;
        $val = $sortedValues[$floorIndex] + ($fraction * ($sortedValues[$ceilIndex] - $sortedValues[$floorIndex]));

        return round($val, 2);
    }

    /**
     * Reset all metrics.
     */
    public function reset(): void
    {
        $this->httpMetrics = [];
        $this->recentQueries = [];
        $this->slowQueries = [];
        $this->potentialNPlusOne = [];
        $this->statusDistribution = [
            '2xx' => 0,
            '3xx' => 0,
            '4xx' => 0,
            '5xx' => 0,
        ];
        $this->connectionDistribution = [];
        $this->totalQueries = 0;
        $this->totalQueryDurationMs = 0.0;
        $this->slowQueryCount = 0;
    }
}
