<?php

namespace MehrabHossain\LogLens\Services;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use MehrabHossain\LogLens\Contracts\QueueMetricsCollectorInterface;
use MehrabHossain\LogLens\LogLens;
use Throwable;

class QueueMetricsCollector implements QueueMetricsCollectorInterface
{
    protected bool $registered = false;

    /**
     * @var array<string, float> Active job start timestamps keyed by job identifier
     */
    protected array $activeJobs = [];

    protected int $processedCount = 0;
    protected int $failedCount = 0;
    protected float $totalDurationMs = 0.0;
    protected int $longRunningCount = 0;

    /**
     * @var array<int, array<string, mixed>>
     */
    protected array $recentProcessed = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    protected array $recentFailed = [];

    protected int $maxSamples;
    protected float $longRunningThresholdMs;

    public function __construct()
    {
        $this->maxSamples = (int) config('loglens.queue.max_samples', 50);
        $this->longRunningThresholdMs = (float) config('loglens.queue.long_running_threshold_ms', 5000);
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
     * Handle JobProcessing event.
     */
    public function handleJobProcessing(JobProcessing $event): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $jobId = $this->resolveJobId($event->job);
        $this->activeJobs[$jobId] = microtime(true);
    }

    /**
     * Handle JobProcessed event.
     */
    public function handleJobProcessed(JobProcessed $event): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $this->processedCount++;
        $jobId = $this->resolveJobId($event->job);
        $startTime = $this->activeJobs[$jobId] ?? null;
        unset($this->activeJobs[$jobId]);

        $durationMs = $startTime ? round((microtime(true) - $startTime) * 1000, 2) : 0.0;
        $this->totalDurationMs += $durationMs;

        $isLongRunning = $durationMs >= $this->longRunningThresholdMs;
        if ($isLongRunning) {
            $this->longRunningCount++;
        }

        $jobName = $event->job->resolveName();

        $this->recentProcessed[] = [
            'id' => $jobId,
            'name' => $jobName,
            'connection' => $event->connectionName,
            'queue' => $event->job->getQueue(),
            'duration_ms' => $durationMs,
            'is_long_running' => $isLongRunning,
            'processed_at' => microtime(true),
        ];

        if (count($this->recentProcessed) > $this->maxSamples) {
            array_shift($this->recentProcessed);
        }
    }

    /**
     * Handle JobFailed event.
     */
    public function handleJobFailed(JobFailed $event): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $this->failedCount++;
        $jobId = $this->resolveJobId($event->job);
        unset($this->activeJobs[$jobId]);

        $jobName = $event->job->resolveName();
        $exception = $event->exception;

        $this->recentFailed[] = [
            'id' => $jobId,
            'name' => $jobName,
            'connection' => $event->connectionName,
            'queue' => $event->job->getQueue(),
            'exception_class' => get_class($exception),
            'exception_message' => $exception->getMessage(),
            'failed_at' => microtime(true),
        ];

        if (count($this->recentFailed) > $this->maxSamples) {
            array_shift($this->recentFailed);
        }
    }

    /**
     * Get aggregate queue metrics summary.
     */
    public function getSummary(): array
    {
        $avgDuration = $this->processedCount > 0
            ? round($this->totalDurationMs / $this->processedCount, 2)
            : 0.0;

        $totalJobs = $this->processedCount + $this->failedCount;
        $failureRate = $totalJobs > 0 ? round(($this->failedCount / $totalJobs) * 100, 2) : 0.0;

        return [
            'enabled' => $this->isEnabled(),
            'processed_count' => $this->processedCount,
            'failed_count' => $this->failedCount,
            'active_jobs_count' => count($this->activeJobs),
            'avg_duration_ms' => $avgDuration,
            'total_duration_ms' => round($this->totalDurationMs, 2),
            'long_running_count' => $this->longRunningCount,
            'long_running_threshold_ms' => $this->longRunningThresholdMs,
            'failure_rate_percent' => $failureRate,
            'backlog' => $this->getBacklog(),
            'recent_failed' => $this->getRecentFailedJobs(10),
            'recent_processed' => $this->getRecentProcessedJobs(10),
        ];
    }

    /**
     * Get recent failed jobs.
     */
    public function getRecentFailedJobs(int $limit = 20): array
    {
        return array_values(array_reverse(array_slice($this->recentFailed, -$limit)));
    }

    /**
     * Get recent processed jobs.
     */
    public function getRecentProcessedJobs(int $limit = 20): array
    {
        return array_values(array_reverse(array_slice($this->recentProcessed, -$limit)));
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
        $this->processedCount = 0;
        $this->failedCount = 0;
        $this->totalDurationMs = 0.0;
        $this->longRunningCount = 0;
        $this->recentProcessed = [];
        $this->recentFailed = [];
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
