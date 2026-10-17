<?php

namespace MehrabHossain\LogLens\Contracts;

interface MetricsStoreInterface
{
    /**
     * Record an individual HTTP request metric.
     *
     * @param  array{
     *     timestamp: float,
     *     duration_ms: float,
     *     status_code: int,
     *     route: string,
     *     is_slow: bool,
     *     correlation_id: string
     * }  $metric
     */
    public function recordHttp(array $metric): void;

    /**
     * Record an individual observed database query event.
     *
     * @param  array{
     *     timestamp: float,
     *     duration_ms: float,
     *     connection: string,
     *     is_slow: bool,
     *     fingerprint: string,
     *     sql: ?string,
     *     correlation_id: ?string,
     *     route: ?string
     * }  $metric
     */
    public function recordQuery(array $metric): void;

    /**
     * Record aggregated request-level query execution data, including potential N+1 warnings.
     *
     * @param  array{
     *     correlation_id: string,
     *     route: string,
     *     duration_ms: float,
     *     query_count: int,
     *     query_duration_ms: float,
     *     slow_query_count: int,
     *     n_plus_one_alerts: array<int, array{
     *         fingerprint: string,
     *         count: int,
     *         total_duration_ms: float,
     *         route: string,
     *         hint: string
     *     }>
     * }  $summary
     */
    public function recordRequestSummary(array $summary): void;

    /**
     * Get aggregated HTTP metrics summary.
     *
     * @return array{
     *     total_requests: int,
     *     avg_response_time_ms: float,
     *     median_response_time_ms: float,
     *     p95_response_time_ms: float,
     *     p99_response_time_ms: float,
     *     slow_request_count: int,
     *     status_distribution: array<string, int>,
     *     recent_requests: array<int, array<string, mixed>>,
     *     recent_slow_requests: array<int, array<string, mixed>>
     * }
     */
    public function getHttpSummary(): array;

    /**
     * Get aggregated database query metrics summary.
     *
     * @return array{
     *     total_queries: int,
     *     total_duration_ms: float,
     *     avg_duration_ms: float,
     *     slow_query_count: int,
     *     n_plus_one_warnings_count: int,
     *     connection_distribution: array<string, int>,
     *     slow_queries: array<int, array<string, mixed>>,
     *     potential_n_plus_one: array<int, array<string, mixed>>
     * }
     */
    public function getDatabaseSummary(): array;

    /**
     * Clear all recorded in-memory metrics.
     */
    public function reset(): void;
}
