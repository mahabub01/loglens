<?php

namespace MehrabHossain\LogLens\Services;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use MehrabHossain\LogLens\Contracts\QueueMetricsCollectorInterface;
use MehrabHossain\LogLens\Contracts\SharedMetricsStorageInterface;
use MehrabHossain\LogLens\LogLens;
use Throwable;

class QueueMetricsCollector implements QueueMetricsCollectorInterface
{
    /**
     * Shared store keys. Queue events fire inside long-running `queue:work`
     * processes, so all metrics must live in the shared store for the
     * dashboard (a separate web process) to read them.
     */
    protected const KEY_PROCESSED_COUNT = 'queue:processed_count';
    protected const KEY_FAILED_COUNT = 'queue:failed_count';
    protected const KEY_TOTAL_DURATION_US = 'queue:total_duration_us';
    protected const KEY_LONG_RUNNING_COUNT = 'queue:long_running_count';
    protected const KEY_RECENT_PROCESSED = 'queue:recent_processed';
    protected const KEY_RECENT_FAILED = 'queue:recent_failed';
    protected const KEY_BUSY_SINCE = 'queue:busy_since';
    protected const HEARTBEAT = 'queue';

    protected bool $registered = false;

    /**
     * When this worker process last wrote its heartbeat (throttles idle-loop writes).
     */
    protected float $lastHeartbeatWrite = 0.0;

    /**
     * @var array<string, float> Active job start timestamps keyed by job identifier (Processing and Processed fire in the same worker)
     */
    protected array $activeJobs = [];

    protected int $maxSamples;
    protected float $longRunningThresholdMs;
    protected int $heartbeatInterval;
    protected int $heartbeatWindow;
    protected int $busyWindow;
    protected SharedMetricsStorageInterface $store;

    public function __construct(?SharedMetricsStorageInterface $store = null)
    {
        $this->store = $store ?? app(SharedMetricsStorageInterface::class);
        $this->maxSamples = (int) config('loglens.queue.max_samples', 50);
        $this->longRunningThresholdMs = (float) config('loglens.queue.long_running_threshold_ms', 5000);
        $this->heartbeatInterval = (int) config('loglens.queue.heartbeat_interval_seconds', 10);
        $this->heartbeatWindow = (int) config('loglens.queue.heartbeat_window_seconds', 60);
        $this->busyWindow = (int) config('loglens.queue.busy_window_seconds', 900);
    }

    /**
     * Register listeners on native Laravel queue events.
     */
    public function register(): void
    {
        if ($this->registered || ! $this->isEnabled()) {
            return;
        }

        $this->registered = true;

        if (class_exists(Looping::class)) {
            Event::listen(Looping::class, function (Looping $event) {
                $this->handleWorkerLooping($event);
            });
        }

        Event::listen(JobProcessing::class, function (JobProcessing $event) {
            $this->handleJobProcessing($event);
        });

        Event::listen(JobProcessed::class, function (JobProcessed $event) {
            $this->handleJobProcessed($event);
        });

        Event::listen(JobFailed::class, function (JobFailed $event) {
            $this->handleJobFailed($event);
        });
    }

    /**
     * Handle Looping event: fired on every worker poll, even when the queue is empty.
     */
    public function handleWorkerLooping(Looping $event): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $this->persist(fn () => $this->recordHeartbeat());
    }

    /**
     * Handle JobProcessing event.
     */
    public function handleJobProcessing(JobProcessing $event): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $now = microtime(true);
        $jobId = $this->resolveJobId($event->job);
        $this->activeJobs[$jobId] = $now;

        // The worker stops looping while a job runs, so mark it busy to avoid a false "off".
        $this->persist(function () use ($now) {
            $this->store->set(self::KEY_BUSY_SINCE, $now, $this->busyWindow);
            $this->recordHeartbeat();
        });
    }

    /**
     * Handle JobProcessed event.
     */
    public function handleJobProcessed(JobProcessed $event): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $jobId = $this->resolveJobId($event->job);
        $startTime = $this->activeJobs[$jobId] ?? null;
        unset($this->activeJobs[$jobId]);

        $durationMs = $startTime ? round((microtime(true) - $startTime) * 1000, 2) : 0.0;
        $isLongRunning = $durationMs >= $this->longRunningThresholdMs;

        $this->persist(function () use ($event, $jobId, $durationMs, $isLongRunning) {
            $this->store->increment(self::KEY_PROCESSED_COUNT);
            // Durations are accumulated as integer microseconds so the counter stays atomic.
            $this->store->increment(self::KEY_TOTAL_DURATION_US, (int) round($durationMs * 1000));
            if ($isLongRunning) {
                $this->store->increment(self::KEY_LONG_RUNNING_COUNT);
            }
            $this->store->forget(self::KEY_BUSY_SINCE);
            $this->recordHeartbeat();

            $this->store->pushHistory(self::KEY_RECENT_PROCESSED, [
                'id' => $jobId,
                'name' => $event->job->resolveName(),
                'connection' => $event->connectionName,
                'queue' => $event->job->getQueue(),
                'duration_ms' => $durationMs,
                'is_long_running' => $isLongRunning,
                'processed_at' => microtime(true),
            ], $this->maxSamples);
        });
    }

    /**
     * Handle JobFailed event.
     */
    public function handleJobFailed(JobFailed $event): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $jobId = $this->resolveJobId($event->job);
        unset($this->activeJobs[$jobId]);

        $this->persist(function () use ($event, $jobId) {
            $this->store->increment(self::KEY_FAILED_COUNT);
            $this->store->forget(self::KEY_BUSY_SINCE);
            $this->recordHeartbeat();

            $exception = $event->exception;

            $this->store->pushHistory(self::KEY_RECENT_FAILED, [
                'id' => $jobId,
                'name' => $event->job->resolveName(),
                'connection' => $event->connectionName,
                'queue' => $event->job->getQueue(),
                'exception_class' => get_class($exception),
                'exception_message' => $exception->getMessage(),
                'failed_at' => microtime(true),
            ], $this->maxSamples);
        });
    }

    /**
     * Get aggregate queue metrics summary.
     */
    public function getSummary(): array
    {
        $processedCount = $this->readInt(self::KEY_PROCESSED_COUNT);
        $failedCount = $this->readInt(self::KEY_FAILED_COUNT);
        $totalDurationMs = $this->readInt(self::KEY_TOTAL_DURATION_US) / 1000;

        $avgDuration = $processedCount > 0
            ? round($totalDurationMs / $processedCount, 2)
            : 0.0;

        $totalJobs = $processedCount + $failedCount;
        $failureRate = $totalJobs > 0 ? round(($failedCount / $totalJobs) * 100, 2) : 0.0;

        $lastHeartbeatAt = $this->lastHeartbeatAt();
        $busySince = $this->read(fn () => $this->store->get(self::KEY_BUSY_SINCE), null);
        $busySince = $busySince !== null ? (float) $busySince : null;
        $status = match (true) {
            $lastHeartbeatAt !== null => 'running',
            $busySince !== null => 'busy',
            default => 'off',
        };

        return [
            'enabled' => $this->isEnabled(),
            'processed_count' => $processedCount,
            'failed_count' => $failedCount,
            'active_jobs_count' => count($this->activeJobs),
            'avg_duration_ms' => $avgDuration,
            'total_duration_ms' => round($totalDurationMs, 2),
            'long_running_count' => $this->readInt(self::KEY_LONG_RUNNING_COUNT),
            'long_running_threshold_ms' => $this->longRunningThresholdMs,
            'failure_rate_percent' => $failureRate,
            'is_alive' => $status !== 'off',
            'status' => $status,
            'last_heartbeat_at' => $lastHeartbeatAt,
            'busy_since' => $busySince,
            'backlog' => $this->getBacklog(),
            'recent_failed' => $this->getRecentFailedJobs(10),
            'recent_processed' => $this->getRecentProcessedJobs(10),
        ];
    }

    /**
     * Get recent failed jobs (newest first).
     */
    public function getRecentFailedJobs(int $limit = 20): array
    {
        return $this->read(fn () => $this->store->getHistory(self::KEY_RECENT_FAILED, $limit), []);
    }

    /**
     * Get recent processed jobs (newest first).
     */
    public function getRecentProcessedJobs(int $limit = 20): array
    {
        return $this->read(fn () => $this->store->getHistory(self::KEY_RECENT_PROCESSED, $limit), []);
    }

    /**
     * Optional queue backlog polling (strictly opt-in via config).
     */
    protected function getBacklog(): ?array
    {
        if (! config('loglens.queue.poll_backlog', false)) {
            return null;
        }

        $queues = config('loglens.queue.monitored_queues', ['default']);
        $backlog = [];

        foreach ($queues as $q) {
            try {
                $backlog[$q] = Queue::size($q);
            } catch (Throwable) {
                $backlog[$q] = null;
            }
        }

        return $backlog;
    }

    /**
     * Reset tracked queue metrics.
     */
    public function reset(): void
    {
        $this->activeJobs = [];
        $this->lastHeartbeatWrite = 0.0;

        $this->persist(function () {
            foreach ([
                self::KEY_PROCESSED_COUNT,
                self::KEY_FAILED_COUNT,
                self::KEY_TOTAL_DURATION_US,
                self::KEY_LONG_RUNNING_COUNT,
                self::KEY_RECENT_PROCESSED,
                self::KEY_RECENT_FAILED,
                self::KEY_BUSY_SINCE,
                'heartbeat:' . self::HEARTBEAT,
            ] as $key) {
                $this->store->forget($key);
            }
        });
    }

    /**
     * Determine if queue monitoring is enabled.
     */
    public function isEnabled(): bool
    {
        return LogLens::isEnabled() && (bool) config('loglens.queue.enabled', true);
    }

    /**
     * Determine if listeners are registered.
     */
    public function isRegistered(): bool
    {
        return $this->registered;
    }

    /**
     * Write the worker heartbeat, at most once per interval per worker process.
     */
    protected function recordHeartbeat(): void
    {
        $now = microtime(true);
        if ($now - $this->lastHeartbeatWrite < $this->heartbeatInterval) {
            return;
        }

        $this->lastHeartbeatWrite = $now;
        $this->store->recordHeartbeat(self::HEARTBEAT, $this->heartbeatWindow);
    }

    /**
     * Timestamp of the last worker heartbeat still inside the liveness window.
     */
    protected function lastHeartbeatAt(): ?float
    {
        $last = (float) $this->read(fn () => $this->store->get('heartbeat:' . self::HEARTBEAT, 0.0), 0.0);

        return $last > 0.0 && (microtime(true) - $last) <= $this->heartbeatWindow ? $last : null;
    }

    /**
     * Run a store write, never letting a storage failure break job processing.
     */
    protected function persist(callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable) {
            // Monitoring must never interrupt queue workers
        }
    }

    /**
     * Run a store read, falling back to a default on failure.
     */
    protected function read(callable $callback, mixed $default): mixed
    {
        try {
            return $callback();
        } catch (Throwable) {
            return $default;
        }
    }

    protected function readInt(string $key): int
    {
        return (int) $this->read(fn () => $this->store->get($key, 0), 0);
    }

    /**
     * Helper to resolve a unique string identifier for a job.
     */
    protected function resolveJobId(mixed $job): string
    {
        if (is_object($job) && method_exists($job, 'getJobId')) {
            $id = $job->getJobId();
            if ($id) {
                return (string) $id;
            }
        }

        return spl_object_hash($job);
    }
}
