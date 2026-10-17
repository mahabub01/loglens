<?php

namespace MehrabHossain\LogLens\Services;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use MehrabHossain\LogLens\Contracts\DatabaseMetricsCollectorInterface;
use MehrabHossain\LogLens\Contracts\MetricsStoreInterface;
use MehrabHossain\LogLens\LogLens;
use MehrabHossain\LogLens\Support\QueryFingerprinter;
use MehrabHossain\LogLens\Support\RequestContext;

class DatabaseMetricsCollector implements DatabaseMetricsCollectorInterface
{
    protected bool $registered = false;

    public function __construct(
        protected MetricsStoreInterface $store,
        protected QueryFingerprinter $fingerprinter,
        protected RequestContext $requestContext
    ) {
    }

    /**
     * Register the passive query event listener on DB::listen.
     */
    public function register(): void
    {
        if ($this->registered || ! $this->isEnabled()) {
            return;
        }

        $this->registered = true;

        DB::listen(function (QueryExecuted $event) {
            $this->handle($event);
        });
    }

    /**
     * Handle an observed QueryExecuted event passively with zero extra queries.
     */
    public function handle(QueryExecuted $event): void
    {
        // Guard against execution if monitoring was turned off
        if (! $this->isEnabled()) {
            return;
        }

        // Strictly NO SQL execution inside this listener!
        $sql = $event->sql;
        $durationMs = (float) $event->time;
        $connectionName = $event->connectionName ?: 'default';

        $fingerprint = $this->fingerprinter->fingerprint($sql);
        $normalizedSql = $this->fingerprinter->normalize($sql);

        $slowThreshold = (float) config('loglens.database.slow_query_threshold_ms', 500);
        $isSlow = $durationMs >= $slowThreshold;

        // 1. Record in active request-scoped context (for request aggregation and N+1 detection)
        $this->requestContext->recordQuery($fingerprint, $durationMs, $isSlow, $normalizedSql);

        // 2. Record in bounded in-memory metrics store
        $this->store->recordQuery([
            'timestamp' => microtime(true),
            'duration_ms' => round($durationMs, 2),
            'connection' => $connectionName,
            'is_slow' => $isSlow,
            'fingerprint' => $fingerprint,
            'sql' => $normalizedSql,
            'correlation_id' => $this->requestContext->getCorrelationId(),
            'route' => $this->requestContext->getRoute(),
        ]);
    }

    /**
     * Determine if database monitoring is currently enabled.
     */
    public function isEnabled(): bool
    {
        return LogLens::isEnabled() && (bool) config('loglens.database.enabled', true);
    }

    /**
     * Determine if the listener has been registered.
     */
    public function isRegistered(): bool
    {
        return $this->registered;
    }
}
