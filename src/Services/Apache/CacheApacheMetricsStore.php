<?php

namespace MehrabHossain\LogLens\Services\Apache;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use MehrabHossain\LogLens\Contracts\ApacheMetricsStoreInterface;

class CacheApacheMetricsStore implements ApacheMetricsStoreInterface
{
    protected string $prefix;
    protected int $historyLimit;
    protected int $ttlSeconds;

    public function __construct(
        ?string $prefix = null,
        ?int $historyLimit = null,
        ?int $ttlSeconds = null
    ) {
        $this->prefix = $prefix ?? (string) config('loglens.apache.cache_prefix', 'loglens:apache');
        $this->historyLimit = $historyLimit ?? (int) config('loglens.apache.history_samples', 60);
        $this->ttlSeconds = $ttlSeconds ?? (int) config('loglens.apache.ttl_seconds', 3600);
    }

    /**
     * Get the cache store repository.
     */
    protected function cache(): Repository
    {
        $storeName = config('loglens.apache.cache_store', null);

        return Cache::store($storeName);
    }

    /**
     * Store the latest Apache metrics sample and append to history.
     */
    public function record(array $metrics): void
    {
        $cache = $this->cache();

        // 1. Save latest snapshot
        $cache->put("{$this->prefix}:latest", $metrics, $this->ttlSeconds);

        // 2. Clear any lingering error since collection succeeded
        $cache->forget("{$this->prefix}:error");

        // 3. Append to history ring-buffer
        $history = $cache->get("{$this->prefix}:history", []);
        if (! is_array($history)) {
            $history = [];
        }

        $history[] = $metrics;

        // Keep within historyLimit
        if (count($history) > $this->historyLimit) {
            $history = array_slice($history, -$this->historyLimit);
        }

        $cache->put("{$this->prefix}:history", $history, $this->ttlSeconds);
    }

    /**
     * Get the latest recorded Apache metrics snapshot.
     */
    public function getLatest(): ?array
    {
        return $this->cache()->get("{$this->prefix}:latest");
    }

    /**
     * Get the recent historical metrics snapshots.
     */
    public function getHistory(int $limit = 60): array
    {
        $history = $this->cache()->get("{$this->prefix}:history", []);
        if (! is_array($history)) {
            return [];
        }

        if ($limit > 0 && count($history) > $limit) {
            return array_slice($history, -$limit);
        }

        return $history;
    }

    /**
     * Determine if the latest metrics snapshot is considered stale.
     */
    public function isStale(int $thresholdSeconds = 5): bool
    {
        $latest = $this->getLatest();
        if ($latest === null) {
            return true;
        }

        $timestamp = (float) ($latest['timestamp'] ?? 0.0);
        $age = microtime(true) - $timestamp;

        return $age > $thresholdSeconds;
    }

    /**
     * Record a collector error message.
     */
    public function recordError(string $error): void
    {
        $this->cache()->put("{$this->prefix}:error", [
            'message' => $error,
            'timestamp' => microtime(true),
        ], $this->ttlSeconds);
    }

    /**
     * Get the last recorded collector error message, if any.
     */
    public function getLastError(): ?string
    {
        $error = $this->cache()->get("{$this->prefix}:error");
        if (is_array($error) && isset($error['message'])) {
            return $error['message'];
        }

        return null;
    }

    /**
     * Clear all stored Apache metrics and errors.
     */
    public function clear(): void
    {
        $cache = $this->cache();
        $cache->forget("{$this->prefix}:latest");
        $cache->forget("{$this->prefix}:history");
        $cache->forget("{$this->prefix}:error");
    }
}
