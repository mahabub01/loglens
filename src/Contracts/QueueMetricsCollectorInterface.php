<?php

namespace MehrabHossain\LogLens\Contracts;

interface QueueMetricsCollectorInterface
{
    /**
     * Register listeners on native Laravel queue events.
     */
    public function register(): void;

    /**
     * Get aggregate queue metrics summary.
     *
     * @return array<string, mixed>
     */
    public function getSummary(): array;

    /**
     * Get recent failed jobs.
     *
     * @param  int  $limit
     * @return array<int, array<string, mixed>>
     */
    public function getRecentFailedJobs(int $limit = 20): array;

    /**
     * Get recent processed jobs.
     *
     * @param  int  $limit
     * @return array<int, array<string, mixed>>
     */
    public function getRecentProcessedJobs(int $limit = 20): array;

    /**
     * Reset tracked queue metrics.
     */
    public function reset(): void;

    /**
     * Determine if queue monitoring is enabled.
     */
    public function isEnabled(): bool;
}
