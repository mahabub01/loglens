<?php

namespace MehrabHossain\LogLens\Contracts;

interface SharedMetricsStorageInterface
{
    /**
     * Check if the shared storage backend is currently available and connected.
     */
    public function isAvailable(): bool;

    /**
     * Get the storage driver name (e.g. 'redis', 'cache', 'memory').
     */
    public function getDriverName(): string;

    /**
     * Store a value under the given key with optional TTL.
     */
    public function set(string $key, mixed $value, int $ttl = 3600): bool;

    /**
     * Retrieve a value by key.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Increment an integer value atomically.
     */
    public function increment(string $key, int $amount = 1): int;

    /**
     * Push a sample into a sliding window history list.
     *
     * @param  array<string, mixed>  $sample
     */
    public function pushHistory(string $key, array $sample, int $maxSamples = 60): void;

    /**
     * Get historical samples from the sliding window.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getHistory(string $key, int $limit = 60): array;

    /**
     * Record a collector heartbeat timestamp.
     */
    public function recordHeartbeat(string $collectorName): void;

    /**
     * Check if a collector heartbeat is alive.
     */
    public function isHeartbeatAlive(string $collectorName, int $ttlSeconds = 10): bool;

    /**
     * Clear all stored metrics.
     */
    public function clear(): void;
}
