<?php

namespace MehrabHossain\LogLens\Contracts;

interface ApacheMetricsStoreInterface
{
    /**
     * Store the latest Apache metrics sample and append to history.
     *
     * @param  array<string, mixed>  $metrics
     */
    public function record(array $metrics): void;

    /**
     * Get the latest recorded Apache metrics snapshot.
     *
     * @return array<string, mixed>|null
     */
    public function getLatest(): ?array;

    /**
     * Get the recent historical metrics snapshots.
     *
     * @param  int  $limit
     * @return array<int, array<string, mixed>>
     */
    public function getHistory(int $limit = 60): array;

    /**
     * Determine if the latest metrics snapshot is considered stale.
     *
     * @param  int  $thresholdSeconds
     */
    public function isStale(int $thresholdSeconds = 5): bool;

    /**
     * Record a collector error message.
     */
    public function recordError(string $error): void;

    /**
     * Get the last recorded collector error message, if any.
     */
    public function getLastError(): ?string;

    /**
     * Clear all stored Apache metrics and errors.
     */
    public function clear(): void;
}
