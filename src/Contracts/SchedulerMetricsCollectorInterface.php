<?php

namespace MehrabHossain\LogLens\Contracts;

interface SchedulerMetricsCollectorInterface
{
    /**
     * Register listeners on native Laravel console scheduler events.
     */
    public function register(): void;

    /**
     * Get aggregate scheduler execution summary.
     *
     * @return array<string, mixed>
     */
    public function getSummary(): array;

    /**
     * Get recent executed tasks.
     *
     * @param  int  $limit
     * @return array<int, array<string, mixed>>
     */
    public function getRecentTasks(int $limit = 20): array;

    /**
     * Reset tracked scheduler metrics.
     */
    public function reset(): void;

    /**
     * Determine if scheduler monitoring is enabled.
     */
    public function isEnabled(): bool;
}
