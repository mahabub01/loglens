<?php

namespace MehrabHossain\LogLens\Services;

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Support\Facades\Event;
use MehrabHossain\LogLens\Contracts\SchedulerMetricsCollectorInterface;
use MehrabHossain\LogLens\Contracts\SharedMetricsStorageInterface;
use MehrabHossain\LogLens\LogLens;
use Throwable;

class SchedulerMetricsCollector implements SchedulerMetricsCollectorInterface
{
    /**
     * Shared store keys. Scheduler events fire inside the short-lived
     * `schedule:run` process, so all metrics must live in the shared store
     * for the dashboard (a separate web process) to read them.
     */
    protected const KEY_TOTAL_RUNS = 'scheduler:total_runs';
    protected const KEY_SUCCESSFUL_RUNS = 'scheduler:successful_runs';
    protected const KEY_FAILED_RUNS = 'scheduler:failed_runs';
    protected const KEY_SKIPPED_RUNS = 'scheduler:skipped_runs';
    protected const KEY_TOTAL_DURATION_US = 'scheduler:total_duration_us';
    protected const KEY_LAST_RUN_AT = 'scheduler:last_run_at';
    protected const KEY_RECENT = 'scheduler:recent';
    protected const HEARTBEAT = 'scheduler';

    /**
     * Artisan commands whose start proves the scheduler is running, even when no task is due.
     */
    protected const SCHEDULER_COMMANDS = ['schedule:run', 'schedule:work'];

    protected bool $registered = false;

    /**
     * @var array<string, float> Active task start timestamps (Starting and Finished fire in the same process)
     */
    protected array $activeTasks = [];

    protected int $maxSamples;
    protected int $ttl;
    protected int $heartbeatWindow;
    protected SharedMetricsStorageInterface $store;

    public function __construct(?SharedMetricsStorageInterface $store = null)
    {
        $this->store = $store ?? app(SharedMetricsStorageInterface::class);
        $this->maxSamples = (int) config('loglens.scheduler.max_samples', 50);
        $this->ttl = (int) config('loglens.metrics.ttl', 604800);
        $this->heartbeatWindow = (int) config('loglens.scheduler.heartbeat_window_seconds', 120);
    }

    /**
     * Register listeners on native Laravel console scheduler events.
     */
    public function register(): void
    {
        if ($this->registered || ! $this->isEnabled()) {
            return;
        }

        $this->registered = true;

        Event::listen(CommandStarting::class, function (CommandStarting $event) {
            $this->handleCommandStarting($event);
        });

        Event::listen(ScheduledTaskStarting::class, function (ScheduledTaskStarting $event) {
            $this->handleTaskStarting($event);
        });

        Event::listen(ScheduledTaskFinished::class, function (ScheduledTaskFinished $event) {
            $this->handleTaskFinished($event);
        });

        if (class_exists(ScheduledTaskFailed::class)) {
            Event::listen(ScheduledTaskFailed::class, function (ScheduledTaskFailed $event) {
                $this->handleTaskFailed($event);
            });
        }

        if (class_exists(ScheduledTaskSkipped::class)) {
            Event::listen(ScheduledTaskSkipped::class, function (ScheduledTaskSkipped $event) {
                $this->handleTaskSkipped($event);
            });
        }
    }

    /**
     * Handle CommandStarting event: cron starting `schedule:run` proves the scheduler is alive.
     */
    public function handleCommandStarting(CommandStarting $event): void
    {
        if (! $this->isEnabled() || ! in_array($event->command, self::SCHEDULER_COMMANDS, true)) {
            return;
        }

        $this->persist(fn () => $this->recordHeartbeat());
    }

    /**
     * Handle ScheduledTaskStarting event.
     */
    public function handleTaskStarting(ScheduledTaskStarting $event): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $taskId = $this->resolveTaskId($event->task);
        $this->activeTasks[$taskId] = microtime(true);

        $this->persist(fn () => $this->recordHeartbeat());
    }

    /**
     * Handle ScheduledTaskFinished event.
     */
    public function handleTaskFinished(ScheduledTaskFinished $event): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $now = microtime(true);
        $durationMs = $this->resolveDuration($event->task, $now);

        $this->persist(function () use ($event, $now, $durationMs) {
            $this->store->increment(self::KEY_TOTAL_RUNS);
            $this->store->increment(self::KEY_SUCCESSFUL_RUNS);
            $this->recordCompletion($now, $durationMs);

            $this->recordTask([
                'command' => $event->task->command ?? $event->task->description ?? 'Closure Task',
                'expression' => $event->task->expression,
                'status' => 'success',
                'duration_ms' => $durationMs,
                'executed_at' => $now,
                'overlap_warning' => false,
            ]);
        });
    }

    /**
     * Handle ScheduledTaskFailed event.
     */
    public function handleTaskFailed(ScheduledTaskFailed $event): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $now = microtime(true);
        $durationMs = $this->resolveDuration($event->task, $now);

        $this->persist(function () use ($event, $now, $durationMs) {
            $this->store->increment(self::KEY_TOTAL_RUNS);
            $this->store->increment(self::KEY_FAILED_RUNS);
            $this->recordCompletion($now, $durationMs);

            $this->recordTask([
                'command' => $event->task->command ?? $event->task->description ?? 'Closure Task',
                'expression' => $event->task->expression,
                'status' => 'failed',
                'duration_ms' => $durationMs,
                'error' => $event->exception->getMessage(),
                'executed_at' => $now,
                'overlap_warning' => false,
            ]);
        });
    }

    /**
     * Handle ScheduledTaskSkipped event (e.g. withoutOverlapping).
     */
    public function handleTaskSkipped(ScheduledTaskSkipped $event): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $taskId = $this->resolveTaskId($event->task);
        unset($this->activeTasks[$taskId]);

        $this->persist(function () use ($event) {
            $this->store->increment(self::KEY_SKIPPED_RUNS);
            $this->recordHeartbeat();

            $this->recordTask([
                'command' => $event->task->command ?? $event->task->description ?? 'Closure Task',
                'expression' => $event->task->expression,
                'status' => 'skipped',
                'duration_ms' => 0.0,
                'executed_at' => microtime(true),
                'overlap_warning' => true,
                'error' => 'Skipped: previous instance is still running (withoutOverlapping).',
            ]);
        });
    }

    /**
     * Pop the task start time and compute its duration in milliseconds.
     */
    protected function resolveDuration(mixed $task, float $now): float
    {
        $taskId = $this->resolveTaskId($task);
        $startTime = $this->activeTasks[$taskId] ?? null;
        unset($this->activeTasks[$taskId]);

        return $startTime ? round(($now - $startTime) * 1000, 2) : 0.0;
    }

    /**
     * Record duration, last run time and heartbeat for a completed run.
     */
    protected function recordCompletion(float $now, float $durationMs): void
    {
        // Durations are accumulated as integer microseconds so the counter stays atomic.
        $this->store->increment(self::KEY_TOTAL_DURATION_US, (int) round($durationMs * 1000));
        $this->store->set(self::KEY_LAST_RUN_AT, $now, $this->ttl);
        $this->recordHeartbeat();
    }

    /**
     * Record the scheduler heartbeat; it expires on its own once the window passes.
     */
    protected function recordHeartbeat(): void
    {
        $this->store->recordHeartbeat(self::HEARTBEAT, $this->heartbeatWindow);
    }

    /**
     * Timestamp of the last scheduler heartbeat still inside the liveness window.
     */
    protected function lastHeartbeatAt(): ?float
    {
        $last = (float) $this->read(fn () => $this->store->get('heartbeat:' . self::HEARTBEAT, 0.0), 0.0);

        return $last > 0.0 && (microtime(true) - $last) <= $this->heartbeatWindow ? $last : null;
    }

    /**
     * Record a task into the shared recent history ring buffer.
     */
    protected function recordTask(array $data): void
    {
        $this->store->pushHistory(self::KEY_RECENT, $data, $this->maxSamples);
    }

    /**
     * Get aggregate scheduler execution summary.
     */
    public function getSummary(): array
    {
        $successfulRuns = $this->readInt(self::KEY_SUCCESSFUL_RUNS);
        $failedRuns = $this->readInt(self::KEY_FAILED_RUNS);
        $completed = $successfulRuns + $failedRuns;
        $totalDurationMs = $this->readInt(self::KEY_TOTAL_DURATION_US) / 1000;
        $avgDuration = $completed > 0 ? round($totalDurationMs / $completed, 2) : 0.0;
        $lastRunAt = $this->read(fn () => $this->store->get(self::KEY_LAST_RUN_AT), null);
        $lastHeartbeatAt = $this->lastHeartbeatAt();

        return [
            'enabled' => $this->isEnabled(),
            'total_runs' => $this->readInt(self::KEY_TOTAL_RUNS),
            'successful_runs' => $successfulRuns,
            'failed_runs' => $failedRuns,
            'skipped_runs' => $this->readInt(self::KEY_SKIPPED_RUNS),
            'avg_duration_ms' => $avgDuration,
            'last_run_at' => $lastRunAt !== null ? (float) $lastRunAt : null,
            'is_alive' => $lastHeartbeatAt !== null,
            'status' => $lastHeartbeatAt !== null ? 'running' : 'off',
            'last_heartbeat_at' => $lastHeartbeatAt,
            'active_tasks_count' => count($this->activeTasks),
            'recent_tasks' => $this->getRecentTasks(15),
        ];
    }

    /**
     * Get recent executed tasks (newest first).
     */
    public function getRecentTasks(int $limit = 20): array
    {
        return $this->read(fn () => $this->store->getHistory(self::KEY_RECENT, $limit), []);
    }

    /**
     * Reset tracked scheduler metrics.
     */
    public function reset(): void
    {
        $this->activeTasks = [];

        $this->persist(function () {
            foreach ([
                self::KEY_TOTAL_RUNS,
                self::KEY_SUCCESSFUL_RUNS,
                self::KEY_FAILED_RUNS,
                self::KEY_SKIPPED_RUNS,
                self::KEY_TOTAL_DURATION_US,
                self::KEY_LAST_RUN_AT,
                self::KEY_RECENT,
                'heartbeat:' . self::HEARTBEAT,
            ] as $key) {
                $this->store->forget($key);
            }
        });
    }

    /**
     * Determine if scheduler monitoring is enabled.
     */
    public function isEnabled(): bool
    {
        return LogLens::isEnabled() && (bool) config('loglens.scheduler.enabled', true);
    }

    /**
     * Determine if listeners are registered.
     */
    public function isRegistered(): bool
    {
        return $this->registered;
    }

    /**
     * Run a store write, never letting a storage failure break the scheduler.
     */
    protected function persist(callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable) {
            // Monitoring must never interrupt scheduled tasks
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
     * Resolve unique task identifier.
     */
    protected function resolveTaskId(mixed $task): string
    {
        if (is_object($task)) {
            if (isset($task->command)) {
                return (string) $task->command;
            }
            if (isset($task->description)) {
                return (string) $task->description;
            }

            return spl_object_hash($task);
        }

        return 'task-' . uniqid();
    }
}
