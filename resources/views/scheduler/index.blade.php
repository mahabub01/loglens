@extends('loglens::layouts.app', ['title' => 'Scheduled Tasks'])

@section('content')
<div class="container-fluid p-0">
    <!-- Notice Banner -->
    <div class="alert alert-info border-info-subtle bg-info-subtle text-info-emphasis d-flex align-items-center justify-content-between p-3 mb-4 rounded-3 shadow-sm" role="alert">
        <div class="d-flex align-items-center gap-3">
            <span class="fs-4"><i class="fa-solid fa-calendar-check text-info"></i></span>
            <div>
                <strong class="d-block mb-1">Laravel Scheduler Observation</strong>
                <span class="small">
                    Observing console scheduled task lifecycle events (<code>ScheduledTaskStarting</code>, <code>ScheduledTaskFinished</code>, <code>ScheduledTaskFailed</code>, <code>ScheduledTaskSkipped</code>).
                </span>
            </div>
        </div>
        <span class="badge bg-{{ $is_enabled ? 'success' : 'secondary' }} text-white d-none d-md-inline-block">
            {{ $is_enabled ? 'Scheduler Observed' : 'Scheduler Standby' }}
        </span>
    </div>

    <!-- Quick Metric Cards -->
    <div class="row g-3 mb-4">
        <!-- Total Task Runs -->
        <div class="col-12 col-sm-6 col-xl-4 col-xxl-2">
            <div class="ll-card p-3 h-100">
                <span class="ll-stat-title">Total Executions</span>
                <div class="ll-stat-value text-light">{{ number_format($summary['total_runs']) }}</div>
                <div class="d-flex align-items-center justify-content-between mt-2 pt-2 border-top border-secondary-subtle">
                    <span class="small text-muted">Runs Observed</span>
                    <span class="badge bg-secondary-subtle text-light ll-badge-pill">Observed</span>
                </div>
            </div>
        </div>

        <!-- Successful Runs -->
        <div class="col-12 col-sm-6 col-xl-4 col-xxl-2">
            <div class="ll-card p-3 h-100">
                <span class="ll-stat-title text-success">Successes</span>
                <div class="ll-stat-value text-success">{{ number_format($summary['successful_runs']) }}</div>
                <div class="d-flex align-items-center justify-content-between mt-2 pt-2 border-top border-secondary-subtle">
                    <span class="small text-muted">Clean Exits</span>
                    <span class="badge bg-success-subtle text-success ll-badge-pill">Passed</span>
                </div>
            </div>
        </div>

        <!-- Failed Runs -->
        <div class="col-12 col-sm-6 col-xl-4 col-xxl-2">
            <div class="ll-card p-3 h-100">
                <span class="ll-stat-title text-danger">Failures</span>
                <div class="ll-stat-value text-{{ $summary['failed_runs'] > 0 ? 'danger' : 'light' }}">
                    {{ number_format($summary['failed_runs']) }}
                </div>
                <div class="d-flex align-items-center justify-content-between mt-2 pt-2 border-top border-secondary-subtle">
                    <span class="small text-muted">Errors</span>
                    <span class="badge bg-{{ $summary['failed_runs'] > 0 ? 'danger' : 'secondary' }}-subtle text-{{ $summary['failed_runs'] > 0 ? 'danger' : 'muted' }} ll-badge-pill">
                        {{ $summary['failed_runs'] > 0 ? 'Alert' : 'None' }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Overlap Skipped Runs -->
        <div class="col-12 col-sm-6 col-xl-4 col-xxl-2">
            <div class="ll-card p-3 h-100">
                <span class="ll-stat-title text-warning">Overlap Skips</span>
                <div class="ll-stat-value text-{{ $summary['skipped_runs'] > 0 ? 'warning' : 'light' }}">
                    {{ number_format($summary['skipped_runs']) }}
                </div>
                <div class="d-flex align-items-center justify-content-between mt-2 pt-2 border-top border-secondary-subtle">
                    <span class="small text-muted">Mutex Locked</span>
                    <span class="badge bg-{{ $summary['skipped_runs'] > 0 ? 'warning' : 'secondary' }}-subtle text-{{ $summary['skipped_runs'] > 0 ? 'warning' : 'muted' }} ll-badge-pill">
                        {{ $summary['skipped_runs'] > 0 ? 'Overlap' : 'None' }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Average Task Duration -->
        <div class="col-12 col-sm-6 col-xl-4 col-xxl-2">
            <div class="ll-card p-3 h-100">
                <span class="ll-stat-title">Avg Duration</span>
                <div class="ll-stat-value text-light">
                    {{ $summary['avg_duration_ms'] }} <span class="fs-6 text-muted">ms</span>
                </div>
                <div class="d-flex align-items-center justify-content-between mt-2 pt-2 border-top border-secondary-subtle">
                    <span class="small text-muted">Execution Timing</span>
                    <span class="small text-muted">Mean</span>
                </div>
            </div>
        </div>

        <!-- Last Execution -->
        <div class="col-12 col-sm-6 col-xl-4 col-xxl-2">
            <div class="ll-card p-3 h-100">
                <span class="ll-stat-title">Last Activity</span>
                <div class="ll-stat-value text-light" style="font-size: 1.35rem;">
                    {{ $summary['last_run_at'] ? date('H:i:s', (int) $summary['last_run_at']) : 'Waiting' }}
                </div>
                <div class="d-flex align-items-center justify-content-between mt-2 pt-2 border-top border-secondary-subtle">
                    <span class="small text-muted">Observed Time</span>
                    <span class="badge bg-secondary-subtle text-light ll-badge-pill">Heartbeat</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Scheduled Tasks Table -->
    <div class="ll-card p-3 mb-4">
        <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom border-secondary-subtle">
            <div>
                <h6 class="mb-0 fw-bold text-light">
                    <i class="fa-solid fa-list-check text-primary me-2"></i> Observed Scheduled Tasks
                </h6>
                <small class="text-muted">History of recent schedule task dispatches</small>
            </div>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle ll-badge-pill">
                {{ count($recent_tasks) }} Recorded
            </span>
        </div>

        @if(count($recent_tasks) > 0)
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle mb-0" style="font-size: 0.85rem;">
                <thead class="text-muted">
                    <tr>
                        <th style="width: 40%;">Task / Command</th>
                        <th style="width: 15%;">Schedule Expression</th>
                        <th style="width: 15%;">Status</th>
                        <th style="width: 15%;">Duration</th>
                        <th style="width: 15%; text-align: right;">Executed At</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recent_tasks as $task)
                    <tr>
                        <td>
                            <strong class="text-light text-break font-monospace">{{ $task['command'] }}</strong>
                            @if(!empty($task['error']))
                                <small class="text-danger d-block mt-1">{{ $task['error'] }}</small>
                            @endif
                        </td>
                        <td>
                            <code class="text-info">{{ $task['expression'] ?? '* * * * *' }}</code>
                        </td>
                        <td>
                            @if($task['status'] === 'success')
                                <span class="badge bg-success-subtle text-success ll-badge-pill"><i class="fa-solid fa-check me-1"></i> Success</span>
                            @elseif($task['status'] === 'failed')
                                <span class="badge bg-danger-subtle text-danger ll-badge-pill"><i class="fa-solid fa-xmark me-1"></i> Failed</span>
                            @elseif($task['status'] === 'skipped')
                                <span class="badge bg-warning-subtle text-warning ll-badge-pill"><i class="fa-solid fa-shield me-1"></i> Skipped (Overlap)</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-secondary-subtle text-light">{{ $task['duration_ms'] }} ms</span>
                        </td>
                        <td class="text-end text-muted">
                            {{ date('H:i:s', (int) $task['executed_at']) }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="p-4 text-center text-muted">
            <i class="fa-solid fa-calendar-xmark fs-2 text-muted opacity-50 mb-2"></i>
            <p class="small mb-0">No scheduled tasks have run yet in the current observation window.</p>
        </div>
        @endif
    </div>
</div>
@endsection
