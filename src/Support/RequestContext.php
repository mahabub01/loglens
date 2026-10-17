<?php

namespace MehrabHossain\LogLens\Support;

class RequestContext
{
    protected ?string $correlationId = null;
    protected ?string $route = null;
    protected ?float $startTime = null;
    protected int $queryCount = 0;
    protected float $queryDurationMs = 0.0;
    protected int $slowQueryCount = 0;

    /**
     * @var array<string, array{count: int, total_duration_ms: float, sample_sql: ?string}>
     */
    protected array $fingerprints = [];

    /**
     * Start the request lifecycle context.
     */
    public function start(string $correlationId, string $route, ?float $startTime = null): void
    {
        $this->reset();
        $this->correlationId = $correlationId;
        $this->route = $route;
        $this->startTime = $startTime ?? microtime(true);
    }

    /**
     * Record a database query observed during this request.
     */
    public function recordQuery(string $fingerprint, float $durationMs, bool $isSlow, ?string $sql = null): void
    {
        $this->queryCount++;
        $this->queryDurationMs += $durationMs;

        if ($isSlow) {
            $this->slowQueryCount++;
        }

        if (! isset($this->fingerprints[$fingerprint])) {
            $this->fingerprints[$fingerprint] = [
                'count' => 1,
                'total_duration_ms' => $durationMs,
                'sample_sql' => $sql,
            ];
        } else {
            $this->fingerprints[$fingerprint]['count']++;
            $this->fingerprints[$fingerprint]['total_duration_ms'] += $durationMs;
        }
    }

    /**
     * Identify potential N+1 query patterns where the same normalized query was repeated.
     */
    public function getPotentialNPlusOneAlerts(int $threshold = 3): array
    {
        $alerts = [];

        foreach ($this->fingerprints as $fp => $data) {
            if ($data['count'] >= $threshold) {
                $alerts[] = [
                    'fingerprint' => $fp,
                    'count' => $data['count'],
                    'total_duration_ms' => round($data['total_duration_ms'], 2),
                    'sample_sql' => $data['sample_sql'],
                    'route' => $this->route ?? 'unknown',
                    'hint' => "Query executed {$data['count']} times in this request. Consider eager loading relationships using with([...]).",
                ];
            }
        }

        return $alerts;
    }

    /**
     * Get aggregate summary of request-level activity.
     */
    public function summary(): array
    {
        $durationMs = $this->startTime ? round((microtime(true) - $this->startTime) * 1000, 2) : 0.0;

        return [
            'correlation_id' => $this->correlationId ?? 'unknown',
            'route' => $this->route ?? 'unknown',
            'duration_ms' => $durationMs,
            'query_count' => $this->queryCount,
            'query_duration_ms' => round($this->queryDurationMs, 2),
            'slow_query_count' => $this->slowQueryCount,
            'n_plus_one_alerts' => $this->getPotentialNPlusOneAlerts(),
        ];
    }

    public function getCorrelationId(): ?string
    {
        return $this->correlationId;
    }

    public function getRoute(): ?string
    {
        return $this->route;
    }

    public function getQueryCount(): int
    {
        return $this->queryCount;
    }

    public function getQueryDurationMs(): float
    {
        return $this->queryDurationMs;
    }

    public function getSlowQueryCount(): int
    {
        return $this->slowQueryCount;
    }

    /**
     * Reset request-scoped context (prevents leaking across requests in Octane / long-running workers).
     */
    public function reset(): void
    {
        $this->correlationId = null;
        $this->route = null;
        $this->startTime = null;
        $this->queryCount = 0;
        $this->queryDurationMs = 0.0;
        $this->slowQueryCount = 0;
        $this->fingerprints = [];
    }
}
