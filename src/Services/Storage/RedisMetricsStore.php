<?php

namespace MehrabHossain\LogLens\Services\Storage;

use Illuminate\Support\Facades\Redis;
use MehrabHossain\LogLens\Contracts\SharedMetricsStorageInterface;
use Throwable;

class RedisMetricsStore implements SharedMetricsStorageInterface
{
    protected string $connection;
    protected string $prefix;
    protected int $ttl;
    protected ?string $lastConnectionError = null;

    public function __construct(?string $connection = null, ?string $prefix = null, ?int $ttl = null)
    {
        $this->connection = $connection ?? (string) config('loglens.redis.connection', 'default');
        $this->prefix = $prefix ?? (string) config('loglens.redis.prefix', 'loglens:metrics');
        $this->ttl = $ttl ?? (int) config('loglens.metrics.ttl', 604800);
    }

    /**
     * Check if Redis is currently reachable and responding to PING.
     */
    public function isAvailable(): bool
    {
        try {
            $redis = $this->redis();
            if (! $redis) {
                return false;
            }

            $response = $redis->ping();

            return $response === true || $response === '+PONG' || $response === 'PONG';
        } catch (Throwable $e) {
            $this->lastConnectionError = $e->getMessage();

            return false;
        }
    }

    public function getDriverName(): string
    {
        return 'redis';
    }

    public function getLastConnectionError(): ?string
    {
        return $this->lastConnectionError;
    }

    /**
     * Store a value under the given key with optional TTL.
     */
    public function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        try {
            $fullKey = "{$this->prefix}:{$key}";
            $encoded = is_scalar($value) ? (string) $value : json_encode($value);

            $this->redis()->setex($fullKey, $ttl, $encoded);

            return true;
        } catch (Throwable $e) {
            $this->lastConnectionError = $e->getMessage();

            return false;
        }
    }

    /**
     * Retrieve a value by key.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        try {
            $fullKey = "{$this->prefix}:{$key}";
            $val = $this->redis()->get($fullKey);

            if ($val === null || $val === false) {
                return $default;
            }

            $decoded = json_decode($val, true);

            return json_last_error() === JSON_ERROR_NONE ? $decoded : $val;
        } catch (Throwable $e) {
            $this->lastConnectionError = $e->getMessage();

            return $default;
        }
    }

    /**
     * Increment an integer value atomically.
     */
    public function increment(string $key, int $amount = 1): int
    {
        try {
            $fullKey = "{$this->prefix}:{$key}";

            return (int) $this->redis()->incrby($fullKey, $amount);
        } catch (Throwable $e) {
            $this->lastConnectionError = $e->getMessage();

            return 0;
        }
    }

    /**
     * Push a sample into a sliding window history list.
     */
    public function pushHistory(string $key, array $sample, int $maxSamples = 60): void
    {
        try {
            $fullKey = "{$this->prefix}:history:{$key}";
            $redis = $this->redis();

            $redis->lpush($fullKey, json_encode($sample));
            $redis->ltrim($fullKey, 0, $maxSamples - 1);
            $redis->expire($fullKey, $this->ttl);
        } catch (Throwable $e) {
            $this->lastConnectionError = $e->getMessage();
        }
    }

    /**
     * Get historical samples from the sliding window.
     */
    public function getHistory(string $key, int $limit = 60): array
    {
        try {
            $fullKey = "{$this->prefix}:history:{$key}";
            $items = $this->redis()->lrange($fullKey, 0, $limit - 1);

            if (! is_array($items)) {
                return [];
            }

            $result = [];
            foreach ($items as $item) {
                $decoded = json_decode($item, true);
                if (is_array($decoded)) {
                    $result[] = $decoded;
                }
            }

            return $result;
        } catch (Throwable $e) {
            $this->lastConnectionError = $e->getMessage();

            return [];
        }
    }

    /**
     * Remove a value and any history list stored under the given key.
     */
    public function forget(string $key): void
    {
        try {
            $this->redis()->del(["{$this->prefix}:{$key}", "{$this->prefix}:history:{$key}"]);
        } catch (Throwable $e) {
            $this->lastConnectionError = $e->getMessage();
        }
    }

    /**
     * Mark a member of a registry set as seen now, pruning members older than the max age.
     */
    public function touchMember(string $set, string $member, int $maxAgeSeconds): void
    {
        try {
            $fullKey = "{$this->prefix}:set:{$set}";
            $now = microtime(true);
            $redis = $this->redis();

            $redis->zadd($fullKey, [$member => $now]);
            $redis->zremrangebyscore($fullKey, '-inf', (string) ($now - $maxAgeSeconds));
            $redis->expire($fullKey, $this->ttl);
        } catch (Throwable $e) {
            $this->lastConnectionError = $e->getMessage();
        }
    }

    /**
     * Get registry members seen within the max age.
     */
    public function getMembers(string $set, int $maxAgeSeconds): array
    {
        try {
            $fullKey = "{$this->prefix}:set:{$set}";
            $members = $this->redis()->zrangebyscore($fullKey, (string) (microtime(true) - $maxAgeSeconds), '+inf', ['withscores' => true]);

            if (! is_array($members)) {
                return [];
            }

            return array_map('floatval', $members);
        } catch (Throwable $e) {
            $this->lastConnectionError = $e->getMessage();

            return [];
        }
    }

    /**
     * Remove a member from a registry set.
     */
    public function removeMember(string $set, string $member): void
    {
        try {
            $this->redis()->zrem("{$this->prefix}:set:{$set}", $member);
        } catch (Throwable $e) {
            $this->lastConnectionError = $e->getMessage();
        }
    }

    /**
     * Record a collector heartbeat timestamp.
     */
    public function recordHeartbeat(string $collectorName, int $ttlSeconds = 60): void
    {
        $this->set("heartbeat:{$collectorName}", microtime(true), $ttlSeconds);
    }

    /**
     * Check if a collector heartbeat is alive.
     */
    public function isHeartbeatAlive(string $collectorName, int $ttlSeconds = 10): bool
    {
        $last = (float) $this->get("heartbeat:{$collectorName}", 0.0);
        if ($last <= 0.0) {
            return false;
        }

        return (microtime(true) - $last) <= $ttlSeconds;
    }

    /**
     * Clear all stored metrics.
     */
    public function clear(): void
    {
        try {
            $keys = $this->redis()->keys("{$this->prefix}:*");
            if (! empty($keys)) {
                $this->redis()->del($keys);
            }
        } catch (Throwable $e) {
            $this->lastConnectionError = $e->getMessage();
        }
    }

    /**
     * Get Redis connection instance.
     */
    protected function redis(): mixed
    {
        return Redis::connection($this->connection);
    }
}
