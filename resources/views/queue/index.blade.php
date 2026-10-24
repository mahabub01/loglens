@extends('loglens::layouts.app', ['title' => 'Queue & Background Jobs'])

@section('content')
<div class="container-fluid p-0">
    <!-- Notice Banner -->
    <div class="alert alert-info border-info-subtle bg-info-subtle text-info-emphasis d-flex align-items-center justify-content-between p-3 mb-4 rounded-3 shadow-sm" role="alert">
        <div class="d-flex align-items-center gap-3">
            <span class="fs-4"><i class="fa-solid fa-clock-rotate-left text-info"></i></span>
            <div>
                <strong class="d-block mb-1">Queue &amp; Background Job Instrumentation</strong>
                <span class="small">
                    Observing native Laravel queue events (<code>JobProcessing</code>, <code>JobProcessed</code>, <code>JobFailed</code>) passively without issuing database queries per refresh.
                </span>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            @if ($is_enabled)
                @switch($summary['status'] ?? 'off')
                    @case('running')
                        <span class="badge bg-success text-white" title="Last heartbeat {{ date('H:i:s', (int) $summary['last_heartbeat_at']) }}">
                            <i class="fa-solid fa-circle fa-2xs me-1"></i> Worker Running ({{ $summary['workers_online'] }} online)
                        </span>
                        @break
                    @case('busy')
                        <span class="badge bg-warning text-dark" title="All workers busy; oldest job started {{ date('H:i:s', (int) $summary['busy_since']) }}">
                            <i class="fa-solid fa-circle fa-2xs me-1"></i> Worker Running (busy, {{ $summary['workers_online'] }} online)
                        </span>
                        @break
                    @default
                        <span class="badge bg-danger text-white" title="No queue:work heartbeat in the last {{ config('loglens.queue.heartbeat_window_seconds', 60) }}s. Check supervisor / queue:work.">
                            <i class="fa-solid fa-circle fa-2xs me-1"></i> Worker Off
                        </span>
                @endswitch
            @endif
            <span class="badge bg-{{ $is_enabled ? 'success' : 'secondary' }} text-white d-none d-md-inline-block">
                {{ $is_enabled ? 'Queue Events Observed' : 'Queue Monitor Standby' }}
            </span>
        </div>
    </div>

    <!-- Active Alerts Banner if any -->
    @if(!empty($alerts))
    <div class="mb-4">
        @foreach($alerts as $alert)
        <div class="alert alert-danger bg-danger-subtle text-danger-emphasis border-danger-subtle p-3 mb-2 rounded-3 d-flex align-items-center justify-content-between shadow-sm" role="alert">
            <div class="d-flex align-items-center gap-3">
                <i class="fa-solid fa-triangle-exclamation fs-4 text-danger"></i>
                <div>
                    <strong>{{ $alert['title'] }}</strong>
                    <span class="d-block small">{{ $alert['message'] }}</span>
                </div>
            </div>
            <span class="badge bg-danger text-white text-uppercase">{{ strtoupper($alert['severity']) }}</span>
        </div>
        @endforeach
    </div>
    @endif

    <!-- Quick Metric Cards -->
    <div class="row g-3 mb-4">
        <!-- Processed Jobs -->
        <div class="col-12 col-sm-6 col-xl-4 col-xxl-2">
            <div class="ll-card p-3 h-100">
                <span class="ll-stat-title text-success">Processed Jobs</span>
                <div class="ll-stat-value text-success">{{ number_format($summary['processed_count']) }}</div>
                <div class="d-flex align-items-center justify-content-between mt-2 pt-2 border-top border-secondary-subtle">
                    <span class="small text-muted">Completed</span>
                    <span class="badge bg-success-subtle text-success ll-badge-pill">Success</span>
                </div>
            </div>
        </div>

        <!-- Failed Jobs -->
        <div class="col-12 col-sm-6 col-xl-4 col-xxl-2">
            <div class="ll-card p-3 h-100">
                <span class="ll-stat-title text-danger">Failed Jobs</span>
                <div class="ll-stat-value text-{{ $summary['failed_count'] > 0 ? 'danger' : 'light' }}">
                    {{ number_format($summary['failed_count']) }}
                </div>
                <div class="d-flex align-items-center justify-content-between mt-2 pt-2 border-top border-secondary-subtle">
                    <span class="small text-muted">Exceptions</span>
                    <span class="badge bg-{{ $summary['failed_count'] > 0 ? 'danger' : 'secondary' }}-subtle text-{{ $summary['failed_count'] > 0 ? 'danger' : 'muted' }} ll-badge-pill">
                        {{ $summary['failed_count'] > 0 ? 'Alert' : 'None' }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Active Jobs -->
        <div class="col-12 col-sm-6 col-xl-4 col-xxl-2">
            <div class="ll-card p-3 h-100">
                <span class="ll-stat-title text-info">In-Flight Jobs</span>
                <div class="ll-stat-value text-info">{{ $summary['active_jobs_count'] }}</div>
                <div class="d-flex align-items-center justify-content-between mt-2 pt-2 border-top border-secondary-subtle">
                    <span class="small text-muted">Workers Busy</span>
                    <span class="badge bg-info-subtle text-info ll-badge-pill">Active</span>
                </div>
            </div>
        </div>

        <!-- Average Job Duration -->
        <div class="col-12 col-sm-6 col-xl-4 col-xxl-2">
            <div class="ll-card p-3 h-100">
                <span class="ll-stat-title">Avg Execution</span>
                <div class="ll-stat-value text-light">
                    {{ $summary['avg_duration_ms'] }} <span class="fs-6 text-muted">ms</span>
                </div>
                <div class="d-flex align-items-center justify-content-between mt-2 pt-2 border-top border-secondary-subtle">
                    <span class="small text-muted">Mean Duration</span>
                    <span class="small text-muted">Timing</span>
                </div>
            </div>
        </div>

        <!-- Long-Running Jobs -->
        <div class="col-12 col-sm-6 col-xl-4 col-xxl-2">
            <div class="ll-card p-3 h-100">
                <span class="ll-stat-title text-warning">Long Running</span>
                <div class="ll-stat-value text-{{ $summary['long_running_count'] > 0 ? 'warning' : 'light' }}">
                    {{ $summary['long_running_count'] }}
                </div>
                <div class="d-flex align-items-center justify-content-between mt-2 pt-2 border-top border-secondary-subtle">
                    <span class="small text-muted">Threshold</span>
                    <span class="badge bg-secondary-subtle text-light ll-badge-pill">&gt; {{ $summary['long_running_threshold_ms'] }} ms</span>
                </div>
            </div>
        </div>

        <!-- Failure Rate -->
        <div class="col-12 col-sm-6 col-xl-4 col-xxl-2">
            <div class="ll-card p-3 h-100">
                <span class="ll-stat-title">Failure Rate</span>
                <div class="ll-stat-value text-{{ $summary['failure_rate_percent'] > 0 ? 'danger' : 'light' }}">
                    {{ $summary['failure_rate_percent'] }}%
                </div>
                <div class="d-flex align-items-center justify-content-between mt-2 pt-2 border-top border-secondary-subtle">
                    <span class="small text-muted">Failed / Total</span>
                    <span class="badge bg-{{ $summary['failure_rate_percent'] > 0 ? 'danger' : 'success' }}-subtle text-{{ $summary['failure_rate_percent'] > 0 ? 'danger' : 'success' }} ll-badge-pill">
                        {{ $summary['failure_rate_percent'] > 0 ? 'Elevated' : 'Clean' }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Queue Workers Table -->
    @php($workers = $summary['workers'] ?? [])
    @php($expectedWorkers = $summary['expected_workers'] ?? null)
    <div class="ll-card p-3 mb-4">
        <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom border-secondary-subtle">
            <div>
                <h6 class="mb-0 fw-bold text-light">
                    <i class="fa-solid fa-gears text-info me-2"></i> Queue Workers
                </h6>
                <small class="text-muted">Live <code>queue:work</code> processes reporting a heartbeat</small>
            </div>
            @if ($expectedWorkers !== null && count($workers) < $expectedWorkers)
                <span class="badge bg-warning text-dark ll-badge-pill" title="loglens.queue.expected_workers = {{ $expectedWorkers }}">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i> {{ count($workers) }} / {{ $expectedWorkers }} Online
                </span>
            @else
                <span class="badge bg-info-subtle text-info border border-info-subtle ll-badge-pill">
                    {{ count($workers) }}{{ $expectedWorkers !== null ? ' / ' . $expectedWorkers : '' }} Online
                </span>
            @endif
        </div>

        @if(count($workers) > 0)
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle mb-0" style="font-size: 0.85rem;">
                <thead class="text-muted">
                    <tr>
                        <th>Worker</th>
                        <th>Queue</th>
                        <th>Status</th>
                        <th>Current Job</th>
                        <th class="text-end">Jobs</th>
                        <th class="text-end">Memory</th>
                        <th class="text-end">Uptime</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($workers as $worker)
                    <tr>
                        <td>
                            <strong class="text-light font-monospace">{{ $worker['id'] }}</strong>
                            <small class="text-muted d-block">last seen {{ date('H:i:s', (int) $worker['last_seen']) }}</small>
                        </td>
                        <td>
                            <span class="badge bg-secondary-subtle text-light">{{ $worker['queue'] ?? '-' }}</span>
                            <small class="text-muted d-block">{{ $worker['connection'] ?? '' }}</small>
                        </td>
                        <td>
                            @if($worker['status'] === 'busy')
                                <span class="badge bg-warning text-dark">Busy</span>
                            @else
                                <span class="badge bg-success text-white">Idle</span>
                            @endif
                        </td>
                        <td>
                            @if($worker['current_job'])
                                <span class="text-light text-break">{{ $worker['current_job'] }}</span>
                                <small class="text-muted d-block">for {{ \Illuminate\Support\Carbon::createFromTimestamp((int) $worker['busy_since'])->diffForHumans(null, true, true) }}</small>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <span class="text-success">{{ number_format($worker['processed']) }}</span>
                            @if($worker['failed'] > 0)
                                <span class="text-danger d-block small">{{ number_format($worker['failed']) }} failed</span>
                            @endif
                        </td>
                        <td class="text-end text-muted">{{ $worker['memory_mb'] }} MB</td>
                        <td class="text-end text-muted">
                            {{ $worker['started_at'] ? \Illuminate\Support\Carbon::createFromTimestamp((int) $worker['started_at'])->diffForHumans(null, true, true) : '-' }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="p-4 text-center text-muted">
            <i class="fa-solid fa-power-off fs-2 text-danger opacity-50 mb-2"></i>
            <p class="small mb-0">No queue workers are reporting. Start <code>php artisan queue:work</code> or check supervisor.</p>
        </div>
        @endif
    </div>

    <!-- Recent Failed Jobs Table -->
    <div class="ll-card p-3 mb-4">
        <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom border-secondary-subtle">
            <div>
                <h6 class="mb-0 fw-bold text-light">
                    <i class="fa-solid fa-circle-exclamation text-danger me-2"></i> Recent Failed Jobs
                </h6>
                <small class="text-muted">Jobs that threw unhandled exceptions during execution</small>
            </div>
            <span class="badge bg-danger-subtle text-danger border border-danger-subtle ll-badge-pill">
                {{ count($recent_failed) }} Logged
            </span>
        </div>

        @if(count($recent_failed) > 0)
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle mb-0" style="font-size: 0.85rem;">
                <thead class="text-muted">
                    <tr>
                        <th style="width: 25%;">Job Name</th>
                        <th style="width: 15%;">Queue</th>
                        <th style="width: 45%;">Exception</th>
                        <th style="width: 15%; text-align: right;">Failed At</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recent_failed as $fail)
                    <tr>
                        <td>
                            <strong class="text-light d-block text-break">{{ $fail['name'] }}</strong>
                            <small class="text-muted">{{ $fail['connection'] }}</small>
                        </td>
                        <td>
                            <span class="badge bg-secondary-subtle text-light">{{ $fail['queue'] }}</span>
                        </td>
                        <td>
                            <span class="badge bg-danger text-white mb-1">{{ class_basename($fail['exception_class']) }}</span>
                            <div class="small text-light text-break font-monospace">{{ Str::limit($fail['exception_message'], 160) }}</div>
                        </td>
                        <td class="text-end text-muted">
                            {{ date('H:i:s', (int) $fail['failed_at']) }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="p-4 text-center text-muted">
            <i class="fa-regular fa-circle-check fs-2 text-success opacity-50 mb-2"></i>
            <p class="small mb-0">No failed queue jobs observed in the current sampling window.</p>
        </div>
        @endif
    </div>

    <!-- Recent Processed Jobs Table -->
    <div class="ll-card p-3 mb-4">
        <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom border-secondary-subtle">
            <div>
                <h6 class="mb-0 fw-bold text-light">
                    <i class="fa-solid fa-list-check text-success me-2"></i> Recent Processed Jobs
                </h6>
                <small class="text-muted">Latest background jobs executed successfully</small>
            </div>
            <span class="badge bg-success-subtle text-success border border-success-subtle ll-badge-pill">
                {{ count($recent_processed) }} Recent
            </span>
        </div>

        @if(count($recent_processed) > 0)
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle mb-0" style="font-size: 0.85rem;">
                <thead class="text-muted">
                    <tr>
                        <th style="width: 40%;">Job Name</th>
                        <th style="width: 20%;">Queue</th>
                        <th style="width: 20%;">Duration</th>
                        <th style="width: 20%; text-align: right;">Processed At</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recent_processed as $job)
                    <tr>
                        <td>
                            <strong class="text-light">{{ $job['name'] }}</strong>
                            <small class="text-muted d-block">{{ $job['connection'] }}</small>
                        </td>
                        <td>
                            <span class="badge bg-secondary-subtle text-light">{{ $job['queue'] }}</span>
                        </td>
                        <td>
                            <span class="badge bg-{{ $job['is_long_running'] ? 'warning text-dark' : 'secondary-subtle text-light' }} ll-badge-pill">
                                {{ $job['duration_ms'] }} ms
                            </span>
                        </td>
                        <td class="text-end text-muted">
                            {{ date('H:i:s', (int) $job['processed_at']) }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="p-4 text-center text-muted">
            <i class="fa-solid fa-inbox fs-2 text-muted opacity-50 mb-2"></i>
            <p class="small mb-0">No processed queue jobs observed yet. Dispatch jobs to see live metrics.</p>
        </div>
        @endif
    </div>
</div>
@endsection
