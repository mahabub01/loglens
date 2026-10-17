<?php

namespace MehrabHossain\LogLens\Services\Storage;

use Illuminate\Support\Facades\Cache;
use MehrabHossain\LogLens\Contracts\SharedMetricsStorageInterface;
use Throwable;

class CacheMetricsStore implements SharedMetricsStorageInterface
{
    protected ?string $storeName;
    protected string $prefix;

    public function __construct(?string $storeName = null, ?string $prefix = null)
    {
        $this->storeName = $storeName;
        $this->prefix = $prefix ?? 'loglens:metrics';
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
            $val = (int) Cache::store($this->storeName)->get($fullKey, 0);
            $val += $amount;
            Cache::store($this->storeName)->put($fullKey, $val, 86400);

            return $val;
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

            Cache::store($this->storeName)->put($fullKey, $history, 86400);
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

    public function recordHeartbeat(string $collectorName): void
    {
        $this->set("heartbeat:{$collectorName}", microtime(true), 60);
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
