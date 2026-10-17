<?php

namespace MehrabHossain\LogLens\Services\Apache;

use MehrabHossain\LogLens\Contracts\ApacheMetricsStoreInterface;

class MemoryApacheMetricsStore implements ApacheMetricsStoreInterface
{
    /**
     * @var array<string, mixed>|null
     */
    protected ?array $latest = null;

    /**
     * @var array<int, array<string, mixed>>
     */
    protected array $history = [];

    protected ?string $lastError = null;
    protected int $historyLimit;

    public function __construct(int $historyLimit = 60)
    {
        $this->historyLimit = $historyLimit;
    }

    public function record(array $metrics): void
    {
        $this->latest = $metrics;
        $this->lastError = null;

        $this->history[] = $metrics;
        if (count($this->history) > $this->historyLimit) {
            array_shift($this->history);
        }
    }

    public function getLatest(): ?array
    {
        return $this->latest;
    }

    public function getHistory(int $limit = 60): array
    {
        if ($limit > 0 && count($this->history) > $limit) {
            return array_slice($this->history, -$limit);
        }

        return $this->history;
    }

    public function isStale(int $thresholdSeconds = 5): bool
    {
        if ($this->latest === null) {
            return true;
        }

        $timestamp = (float) ($this->latest['timestamp'] ?? 0.0);
        $age = microtime(true) - $timestamp;

        return $age > $thresholdSeconds;
    }

    public function recordError(string $error): void
    {
        $this->lastError = $error;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function clear(): void
    {
        $this->latest = null;
        $this->history = [];
        $this->lastError = null;
    }
}
