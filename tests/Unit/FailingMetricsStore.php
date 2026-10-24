<?php

namespace MehrabHossain\LogLens\Tests\Unit;

use MehrabHossain\LogLens\Contracts\SharedMetricsStorageInterface;
use RuntimeException;

/**
 * Shared store that throws on every call, used to prove collectors swallow storage failures.
 */
class FailingMetricsStore implements SharedMetricsStorageInterface
{
    public function isAvailable(): bool
    {
        throw new RuntimeException('Store unavailable');
    }

    public function getDriverName(): string
    {
        return 'failing';
    }

    public function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        throw new RuntimeException('Store unavailable');
    }

    public function get(string $key, mixed $default = null): mixed
    {
        throw new RuntimeException('Store unavailable');
    }

    public function increment(string $key, int $amount = 1): int
    {
        throw new RuntimeException('Store unavailable');
    }

    public function pushHistory(string $key, array $sample, int $maxSamples = 60): void
    {
        throw new RuntimeException('Store unavailable');
    }

    public function getHistory(string $key, int $limit = 60): array
    {
        throw new RuntimeException('Store unavailable');
    }

    public function forget(string $key): void
    {
        throw new RuntimeException('Store unavailable');
    }

    public function recordHeartbeat(string $collectorName, int $ttlSeconds = 60): void
    {
        throw new RuntimeException('Store unavailable');
    }

    public function isHeartbeatAlive(string $collectorName, int $ttlSeconds = 10): bool
    {
        throw new RuntimeException('Store unavailable');
    }

    public function clear(): void
    {
        throw new RuntimeException('Store unavailable');
    }
}
