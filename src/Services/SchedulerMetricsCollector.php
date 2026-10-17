<?php

namespace MehrabHossain\LogLens\Services;

use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Support\Facades\Event;
use MehrabHossain\LogLens\Contracts\SchedulerMetricsCollectorInterface;
use MehrabHossain\LogLens\LogLens;

class SchedulerMetricsCollector implements SchedulerMetricsCollectorInterface
{
    protected bool $registered = false;

    /**
     * @var array<string, float> Active task start timestamps
     */
    protected array $activeTasks = [];

    protected int $totalRuns = 0;
    protected int $successfulRuns = 0;
    protected int $failedRuns = 0;
    protected int $skippedRuns = 0;
    protected float $totalDurationMs = 0.0;
    protected ?float $lastRunAt = null;

    /**
     * @var array<int, array<string, mixed>>
     */
    protected array $recentTasks = [];

    protected int $maxSamples;

    public function __construct()
    {
        $this->maxSamples = (int) config('loglens.scheduler.max_samples', 50);
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
     * Handle ScheduledTaskStarting event.
     */
    public function handleTaskStarting(ScheduledTaskStarting $event): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $taskId = $this->resolveTaskId($event->task);
        $this->activeTasks[$taskId] = microtime(true);
    }

    /**
     * Handle ScheduledTaskFinished event.
     */
    public function handleTaskFinished(ScheduledTaskFinished $event): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $this->totalRuns++;
        $this->successfulRuns++;
        $this->lastRunAt = microtime(true);

        $taskId = $this->resolveTaskId($event->task);
        $startTime = $this->activeTasks[$taskId] ?? null;
        unset($this->activeTasks[$taskId]);

        $durationMs = $startTime ? round((microtime(true) - $startTime) * 1000, 2) : 0.0;
        $this->totalDurationMs += $durationMs;

        $this->recordTask([
            'command' => $event->task->command ?? $event->task->description ?? 'Closure Task',
            'expression' => $event->task->expression,
            'status' => 'success',
            'duration_ms' => $durationMs,
            'executed_at' => $this->lastRunAt,
            'overlap_warning' => false,
        ]);
    }

    /**
     * Handle ScheduledTaskFailed event.
     */
    public function handleTaskFailed(ScheduledTaskFailed $event): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $this->totalRuns++;
        $this->failedRuns++;
        $this->lastRunAt = microtime(true);

        $taskId = $this->resolveTaskId($event->task);
        $startTime = $this->activeTasks[$taskId] ?? null;
        unset($this->activeTasks[$taskId]);

        $durationMs = $startTime ? round((microtime(true) - $startTime) * 1000, 2) : 0.0;
        $this->totalDurationMs += $durationMs;

        $this->recordTask([
            'command' => $event->task->command ?? $event->task->description ?? 'Closure Task',
            'expression' => $event->task->expression,
            'status' => 'failed',
            'duration_ms' => $durationMs,
            'error' => $event->exception->getMessage(),
            'executed_at' => $this->lastRunAt,
            'overlap_warning' => false,
        ]);
    }

    /**
     * Handle ScheduledTaskSkipped event (e.g. withoutOverlapping).
     */
    public function handleTaskSkipped(ScheduledTaskSkipped $event): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $this->skippedRuns++;
        $taskId = $this->resolveTaskId($event->task);
        unset($this->activeTasks[$taskId]);

        $this->recordTask([
            'command' => $event->task->command ?? $event->task->description ?? 'Closure Task',
            'expression' => $event->task->expression,
            'status' => 'skipped',
            'duration_ms' => 0.0,
            'executed_at' => microtime(true),
            'overlap_warning' => true,
            'error' => 'Skipped: previous instance is still running (withoutOverlapping).',
        ]);
    }

    /**
     * Record a task into recent history ring buffer.
     */
    protected function recordTask(array $data): void
    {
        $this->recentTasks[] = $data;
        if (count($this->recentTasks) > $this->maxSamples) {
            array_shift($this->recentTasks);
        }
    }

    /**
     * Get aggregate scheduler execution summary.
     */
    public function getSummary(): array
    {
        $completed = $this->successfulRuns + $this->failedRuns;
        $avgDuration = $completed > 0 ? round($this->totalDurationMs / $completed, 2) : 0.0;

        return [
            'enabled' => $this->isEnabled(),
            'total_runs' => $this->totalRuns,
            'successful_runs' => $this->successfulRuns,
            'failed_runs' => $this->failedRuns,
            'skipped_runs' => $this->skippedRuns,
            'avg_duration_ms' => $avgDuration,
            'last_run_at' => $this->lastRunAt,
            'active_tasks_count' => count($this->activeTasks),
            'recent_tasks' => $this->getRecentTasks(15),
        ];
    }

    /**
     * Get recent executed tasks.
     */
    public function getRecentTasks(int $limit = 20): array
    {
        return array_values(array_reverse(array_slice($this->recentTasks, -$limit)));
    }

    /**
     * Reset tracked scheduler metrics.
     */
    public function reset(): void
    {
        $this->activeTasks = [];
        $this->totalRuns = 0;
        $this->successfulRuns = 0;
        $this->failedRuns = 0;
        $this->skippedRuns = 0;
        $this->totalDurationMs = 0.0;
        $this->lastRunAt = null;
        $this->recentTasks = [];
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
