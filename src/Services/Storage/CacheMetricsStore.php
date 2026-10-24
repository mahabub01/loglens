<?php

namespace MehrabHossain\LogLens\Services\Storage;

use Illuminate\Support\Facades\Cache;
use MehrabHossain\LogLens\Contracts\SharedMetricsStorageInterface;
use Throwable;

class CacheMetricsStore implements SharedMetricsStorageInterface
{
    protected ?string $storeName;
    protected string $prefix;
    protected int $ttl;

    public function __construct(?string $storeName = null, ?string $prefix = null, ?int $ttl = null)
    {
        $this->storeName = $storeName;
        $this->prefix = $prefix ?? 'loglens:metrics';
        $this->ttl = $ttl ?? (int) config('loglens.metrics.ttl', 604800);
    }

    public function isAvailable(): bool
    {
        try {
            $key = "{$this->prefix}:probe";
            Cache::store($this->storeName)->put($key, 1, 5);

            return Cache::store($this->storeName)->get($key) === 1;
        } catch (Throwable) {
            return false;
        }
    }

    public function getDriverName(): string
    {
        return 'cache';
    }

    public function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        try {
            Cache::store($this->storeName)->put("{$this->prefix}:{$key}", $value, $ttl);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        try {
            return Cache::store($this->storeName)->get("{$this->prefix}:{$key}", $default);
        } catch (Throwable) {
            return $default;
        }
    }

    public function increment(string $key, int $amount = 1): int
    {
        try {
            $fullKey = "{$this->prefix}:{$key}";
            $cache = Cache::store($this->storeName);

            // Seed the counter (no-op when it already exists), then let the
            // driver increment it atomically so concurrent workers never lose counts.
            $cache->add($fullKey, 0, $this->ttl);

            return (int) $cache->increment($fullKey, $amount);
        } catch (Throwable) {
            return 0;
        }
    }

    public function pushHistory(string $key, array $sample, int $maxSamples = 60): void
    {
        try {
            $fullKey = "{$this->prefix}:history:{$key}";
            $history = Cache::store($this->storeName)->get($fullKey, []);
            if (! is_array($history)) {
                $history = [];
            }

            array_unshift($history, $sample);
            if (count($history) > $maxSamples) {
                $history = array_slice($history, 0, $maxSamples);
            }

            Cache::store($this->storeName)->put($fullKey, $history, $this->ttl);
        } catch (Throwable) {
            // Drop on failure
        }
    }

    public function getHistory(string $key, int $limit = 60): array
    {
        try {
            $fullKey = "{$this->prefix}:history:{$key}";
            $history = Cache::store($this->storeName)->get($fullKey, []);
            if (! is_array($history)) {
                return [];
            }

            return array_slice($history, 0, $limit);
        } catch (Throwable) {
            return [];
        }
    }

    public function forget(string $key): void
    {
        try {
            Cache::store($this->storeName)->forget("{$this->prefix}:{$key}");
            Cache::store($this->storeName)->forget("{$this->prefix}:history:{$key}");
        } catch (Throwable) {
            // Ignore store failures
        }
    }

    public function recordHeartbeat(string $collectorName, int $ttlSeconds = 60): void
    {
        $this->set("heartbeat:{$collectorName}", microtime(true), $ttlSeconds);
    }

    public function isHeartbeatAlive(string $collectorName, int $ttlSeconds = 10): bool
    {
        $last = (float) $this->get("heartbeat:{$collectorName}", 0.0);
        if ($last <= 0.0) {
            return false;
        }

        return (microtime(true) - $last) <= $ttlSeconds;
    }

    public function clear(): void
    {
        // Cache wipe probe
    }
}
