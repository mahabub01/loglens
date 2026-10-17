<?php

namespace MehrabHossain\LogLens\Tests\Unit;

use Illuminate\Support\Facades\Redis;
use MehrabHossain\LogLens\Services\Storage\RedisMetricsStore;
use MehrabHossain\LogLens\Tests\TestCase;

class RedisMetricsStoreTest extends TestCase
{
    public function test_handles_unavailable_redis_gracefully_without_crashing(): void
    {
        // Point to an invalid unreachable host
        config(['database.redis.default.host' => '192.0.2.1']); // RFC 5737 TEST-NET (unroutable)
        config(['database.redis.default.port' => 6379]);

        $store = new RedisMetricsStore(connection: 'default', prefix: 'test:metrics');

        $this->assertFalse($store->isAvailable());
        $this->assertNotNull($store->getLastConnectionError());
        $this->assertFalse($store->set('key', 'val'));
        $this->assertSame('default_val', $store->get('key', 'default_val'));
        $this->assertSame(0, $store->increment('counter'));
        $this->assertEmpty($store->getHistory('metric'));
        $this->assertFalse($store->isHeartbeatAlive('system'));
    }

    public function test_operates_normally_when_redis_is_available(): void
    {
        $mockConnection = new class {
            public array $storage = [];

            public function ping(): string
            {
                return 'PONG';
            }

            public function setex(string $key, int $ttl, string $val): bool
            {
                $this->storage[$key] = $val;
                return true;
            }

            public function get(string $key): ?string
            {
                return $this->storage[$key] ?? null;
            }

            public function incrby(string $key, int $amt): int
            {
                $val = (int) ($this->storage[$key] ?? 0) + $amt;
                $this->storage[$key] = (string) $val;
                return $val;
            }

            public function lpush(string $key, string $val): int
            {
                if (! isset($this->storage[$key]) || ! is_array($this->storage[$key])) {
                    $this->storage[$key] = [];
                }
                array_unshift($this->storage[$key], $val);
                return count($this->storage[$key]);
            }

            public function ltrim(string $key, int $start, int $stop): bool
            {
                if (isset($this->storage[$key]) && is_array($this->storage[$key])) {
                    $this->storage[$key] = array_slice($this->storage[$key], $start, $stop - $start + 1);
                }
                return true;
            }

            public function expire(string $key, int $ttl): bool
            {
                return true;
            }

            public function lrange(string $key, int $start, int $stop): array
            {
                if (! isset($this->storage[$key]) || ! is_array($this->storage[$key])) {
                    return [];
                }
                return array_slice($this->storage[$key], $start, $stop - $start + 1);
            }
        };

        Redis::shouldReceive('connection')
            ->with('default')
            ->andReturn($mockConnection);

        $store = new RedisMetricsStore(connection: 'default', prefix: 'loglens:test');

        $this->assertTrue($store->isAvailable());
        $this->assertSame('redis', $store->getDriverName());

        // Store & Get
        $this->assertTrue($store->set('sample_key', ['val' => 42]));
        $retrieved = $store->get('sample_key');
        $this->assertSame(42, $retrieved['val']);

        // Increment
        $this->assertSame(1, $store->increment('counter', 1));
        $this->assertSame(6, $store->increment('counter', 5));

        // Sliding history
        $store->pushHistory('cpu', ['timestamp' => 100, 'val' => 50]);
        $store->pushHistory('cpu', ['timestamp' => 101, 'val' => 55]);
        $history = $store->getHistory('cpu', 10);
        $this->assertCount(2, $history);
        $this->assertSame(55, $history[0]['val']);

        // Heartbeat
        $store->recordHeartbeat('test_worker');
        $this->assertTrue($store->isHeartbeatAlive('test_worker', 10));
    }

    public function test_detects_stale_metrics_and_expired_heartbeats(): void
    {
        $mockConnection = new class {
            public array $storage = [];
            public function ping(): string { return 'PONG'; }
            public function setex(string $key, int $ttl, string $val): bool { $this->storage[$key] = $val; return true; }
            public function get(string $key): ?string { return $this->storage[$key] ?? null; }
        };

        Redis::shouldReceive('connection')
            ->with('default')
            ->andReturn($mockConnection);

        $store = new RedisMetricsStore(connection: 'default', prefix: 'loglens:test');

        // Record a stale heartbeat from 60 seconds ago
        $staleTimestamp = microtime(true) - 60;
        $store->set('heartbeat:stale_worker', $staleTimestamp);

        // Record fresh heartbeat
        $freshTimestamp = microtime(true) - 2;
        $store->set('heartbeat:fresh_worker', $freshTimestamp);

        $this->assertFalse($store->isHeartbeatAlive('stale_worker', 10), 'Heartbeat older than threshold must be detected as stale.');
        $this->assertTrue($store->isHeartbeatAlive('fresh_worker', 10), 'Recent heartbeat within threshold must be detected as alive.');
        $this->assertFalse($store->isHeartbeatAlive('nonexistent_worker', 10), 'Unseen worker must report as unavailable.');
    }

    public function test_multi_worker_metric_aggregation_and_bounded_retention(): void
    {
        $mockConnection = new class {
            public array $storage = [];
            public function ping(): string { return 'PONG'; }
            public function incrby(string $key, int $amt): int
            {
                $val = (int) ($this->storage[$key] ?? 0) + $amt;
                $this->storage[$key] = (string) $val;
                return $val;
            }
            public function lpush(string $key, string $val): int
            {
                if (! isset($this->storage[$key]) || ! is_array($this->storage[$key])) {
                    $this->storage[$key] = [];
                }
                array_unshift($this->storage[$key], $val);
                return count($this->storage[$key]);
            }
            public function ltrim(string $key, int $start, int $stop): bool
            {
                if (isset($this->storage[$key]) && is_array($this->storage[$key])) {
                    $this->storage[$key] = array_slice($this->storage[$key], $start, $stop - $start + 1);
                }
                return true;
            }
            public function expire(string $key, int $ttl): bool { return true; }
            public function lrange(string $key, int $start, int $stop): array
            {
                if (! isset($this->storage[$key]) || ! is_array($this->storage[$key])) {
                    return [];
                }
                return array_slice($this->storage[$key], $start, $stop - $start + 1);
            }
        };

        Redis::shouldReceive('connection')
            ->with('default')
            ->andReturn($mockConnection);

        // Multi-worker aggregation
        $worker1 = new RedisMetricsStore(connection: 'default', prefix: 'loglens:shared');
        $worker2 = new RedisMetricsStore(connection: 'default', prefix: 'loglens:shared');

        $worker1->increment('jobs_processed', 5);
        $worker2->increment('jobs_processed', 3);
        $finalCount = $worker1->increment('jobs_processed', 2);

        $this->assertSame(10, $finalCount, 'Multiple workers must aggregate atomically into shared counters.');

        // Bounded retention (sliding history)
        for ($i = 1; $i <= 10; $i++) {
            $worker1->pushHistory('cpu_usage', ['sample' => $i], maxSamples: 4);
        }

        $history = $worker1->getHistory('cpu_usage', 20);
        $this->assertCount(4, $history, 'History storage must be strictly bounded by maxSamples.');
        $this->assertSame(10, $history[0]['sample'], 'Most recent sample should be at index 0.');
    }
}
