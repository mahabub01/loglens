<?php

namespace MehrabHossain\LogLens\Services;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\Looping;
use Illuminate\Queue\Events\WorkerStopping;
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

    /**
     * Worker registry: each worker writes its own state key, and the set lists worker ids
     * because cache stores cannot enumerate keys.
     */
    protected const WORKER_SET = 'queue:workers';
    protected const KEY_WORKER_PREFIX = 'queue:worker:';
    protected const REGISTRY_INTERVAL_SECONDS = 60;

    protected bool $registered = false;

    /**
     * Whether this process is a queue worker daemon (Looping fired). Jobs run inline
     * by the sync driver inside web requests must not register phantom workers.
     */
    protected bool $isWorkerProcess = false;

    protected string $workerId;
    protected ?float $workerStartedAt = null;
    protected ?string $workerConnection = null;
    protected ?string $workerQueue = null;
    protected int $workerProcessed = 0;
    protected int $workerFailed = 0;

    /**
     * @var array{name: string, started_at: float}|null
     */
    protected ?array $currentJob = null;

    /**
     * When this worker last wrote its state / touched the registry (throttles idle-loop writes).
     */
    protected float $lastHeartbeatWrite = 0.0;
    protected float $lastRegistryTouch = 0.0;

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

    public function __construct(?SharedMetricsStorageInterface $store = null, ?string $workerId = null)
    {
        $this->store = $store ?? app(SharedMetricsStorageInterface::class);
        $this->workerId = $workerId ?? (gethostname() ?: 'unknown') . ':' . getmypid();
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

        if (class_exists(WorkerStopping::class)) {
            Event::listen(WorkerStopping::class, function (WorkerStopping $event) {
                $this->handleWorkerStopping($event);
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

        $this->isWorkerProcess = true;
        $this->workerStartedAt ??= microtime(true);
        $this->workerConnection = $event->connectionName;
        $this->workerQueue = $event->queue;

        // Looping only fires between jobs, so a job that ended without JobProcessed
        // (e.g. released for retry) is finished by now.
        $wasBusy = $this->currentJob !== null;
        $this->currentJob = null;

        $this->persist(fn () => $this->writeWorkerState(force: $wasBusy));
    }

    /**
     * Handle WorkerStopping event: remove this worker from the registry immediately.
     */
    public function handleWorkerStopping(WorkerStopping $event): void
    {
        if (! $this->isEnabled() || ! $this->isWorkerProcess) {
            return;
        }

        $this->persist(function () {
            $this->store->forget(self::KEY_WORKER_PREFIX . $this->workerId);
            $this->store->removeMember(self::WORKER_SET, $this->workerId);
        });
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

        // The worker stops looping while a job runs, so publish "busy" now to avoid a false "off".
        $this->currentJob = ['name' => $event->job->resolveName(), 'started_at' => $now];
        $this->persist(fn () => $this->writeWorkerState(force: true));
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

        $this->workerProcessed++;
        $this->currentJob = null;

        $this->persist(function () use ($event, $jobId, $durationMs, $isLongRunning) {
            $this->store->increment(self::KEY_PROCESSED_COUNT);
            // Durations are accumulated as integer microseconds so the counter stays atomic.
            $this->store->increment(self::KEY_TOTAL_DURATION_US, (int) round($durationMs * 1000));
            if ($isLongRunning) {
                $this->store->increment(self::KEY_LONG_RUNNING_COUNT);
            }
            $this->writeWorkerState(force: true);

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

        $this->workerFailed++;
        $this->currentJob = null;

        $this->persist(function () use ($event, $jobId) {
            $this->store->increment(self::KEY_FAILED_COUNT);
            $this->writeWorkerState(force: true);

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

        $workers = $this->getWorkers();
        $busyWorkers = array_values(array_filter($workers, fn (array $w) => $w['status'] === 'busy'));
        $status = match (true) {
            count($workers) > count($busyWorkers) => 'running',
            count($busyWorkers) > 0 => 'busy',
            default => 'off',
        };
        $expectedWorkers = config('loglens.queue.expected_workers');
        $busySince = $busyWorkers ? min(array_column($busyWorkers, 'busy_since')) : null;

        return [
            'enabled' => $this->isEnabled(),
            'processed_count' => $processedCount,
            'failed_count' => $failedCount,
            'active_jobs_count' => count($busyWorkers),
            'avg_duration_ms' => $avgDuration,
            'total_duration_ms' => round($totalDurationMs, 2),
            'long_running_count' => $this->readInt(self::KEY_LONG_RUNNING_COUNT),
            'long_running_threshold_ms' => $this->longRunningThresholdMs,
            'failure_rate_percent' => $failureRate,
            'is_alive' => $status !== 'off',
            'status' => $status,
            'last_heartbeat_at' => $workers ? max(array_column($workers, 'last_seen')) : null,
            'busy_since' => $busySince !== null ? (float) $busySince : null,
            'workers' => $workers,
            'workers_online' => count($workers),
            'expected_workers' => is_numeric($expectedWorkers) ? (int) $expectedWorkers : null,
            'backlog' => $this->getBacklog(),
            'recent_failed' => $this->getRecentFailedJobs(10),
            'recent_processed' => $this->getRecentProcessedJobs(10),
        ];
    }

    /**
     * Get the queue workers that are currently alive (idle within the heartbeat window, or busy).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getWorkers(): array
    {
        // Busy workers stop touching the registry, so keep their ids for the whole busy window.
        $ids = $this->read(fn () => $this->store->getMembers(self::WORKER_SET, $this->busyWindow + self::REGISTRY_INTERVAL_SECONDS), []);
        $now = microtime(true);
        $workers = [];

        foreach (array_keys($ids) as $id) {
            $worker = $this->read(fn () => $this->store->get(self::KEY_WORKER_PREFIX . $id), null);
            if (! is_array($worker)) {
                continue; // State key expired: the worker died or stopped
            }

            $isBusy = ($worker['status'] ?? 'idle') === 'busy'
                && $now - (float) ($worker['busy_since'] ?? 0) <= $this->busyWindow;
            $isAlive = $now - (float) ($worker['last_seen'] ?? 0) <= $this->heartbeatWindow;

            if (! $isBusy && ! $isAlive) {
                continue;
            }

            $worker['status'] = $isBusy ? 'busy' : 'idle';
            $workers[] = $worker;
        }

        usort($workers, fn (array $a, array $b) => strcmp((string) $a['id'], (string) $b['id']));

        return $workers;
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
     * Reset tracked queue metrics. Live worker state is not a metric and is kept.
     */
    public function reset(): void
    {
        $this->activeJobs = [];

        $this->persist(function () {
            foreach ([
                self::KEY_PROCESSED_COUNT,
                self::KEY_FAILED_COUNT,
                self::KEY_TOTAL_DURATION_US,
                self::KEY_LONG_RUNNING_COUNT,
                self::KEY_RECENT_PROCESSED,
                self::KEY_RECENT_FAILED,
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
     * Publish this worker's state. Idle-loop writes are throttled to one per interval;
     * job start/finish force a write so busy/idle changes show up immediately.
     */
    protected function writeWorkerState(bool $force = false): void
    {
        if (! $this->isWorkerProcess) {
            return;
        }

        $now = microtime(true);
        if (! $force && $now - $this->lastHeartbeatWrite < $this->heartbeatInterval) {
            return;
        }

        $this->lastHeartbeatWrite = $now;
        $isBusy = $this->currentJob !== null;

        // A busy worker stops writing until its job ends, so its state must outlive the job.
        $this->store->set(self::KEY_WORKER_PREFIX . $this->workerId, [
            'id' => $this->workerId,
            'hostname' => gethostname() ?: 'unknown',
            'pid' => getmypid(),
            'connection' => $this->workerConnection,
            'queue' => $this->workerQueue,
            'started_at' => $this->workerStartedAt,
            'last_seen' => $now,
            'status' => $isBusy ? 'busy' : 'idle',
            'current_job' => $this->currentJob['name'] ?? null,
            'busy_since' => $this->currentJob['started_at'] ?? null,
            'processed' => $this->workerProcessed,
            'failed' => $this->workerFailed,
            'memory_mb' => round(memory_get_usage(true) / 1048576, 1),
        ], $isBusy ? max($this->busyWindow, $this->heartbeatWindow) : $this->heartbeatWindow);

        if ($now - $this->lastRegistryTouch >= self::REGISTRY_INTERVAL_SECONDS) {
            $this->lastRegistryTouch = $now;
            $this->store->touchMember(self::WORKER_SET, $this->workerId, $this->busyWindow + self::REGISTRY_INTERVAL_SECONDS);
        }
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
