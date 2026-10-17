@extends('loglens::layouts.app', ['title' => 'Database Query Observation'])

@section('content')
<div class="container-fluid p-0">
    <!-- Strict Zero-Extra-Query Mode Banner -->
    <div class="alert alert-success border-success-subtle bg-success-subtle text-success-emphasis d-flex align-items-center justify-content-between p-3 mb-4 rounded-3 shadow-sm" role="alert">
        <div class="d-flex align-items-center gap-3">
            <span class="fs-4"><i class="fa-solid fa-shield-halved text-success"></i></span>
            <div>
                <strong class="d-block mb-1">Strict Zero-Extra-Query Mode Active</strong>
                <span class="small">
                    LogLens observes your application's natural database query events via <code>DB::listen</code>. It <strong>never</strong> runs monitoring SQL statements, never inserts a row per query event, and requires <strong>zero database migrations</strong>.
                </span>
            </div>
        </div>
        <span class="badge bg-success text-white d-none d-md-inline-block">
            0 Monitoring Queries Guaranteed
        </span>
    </div>

    <!-- Quick Metric Cards -->
    <div class="row g-3 mb-4">
        <!-- Total Queries -->
        <div class="col-12 col-sm-6 col-xl-4 col-xxl-2">
            <div class="ll-card p-3 h-100">
                <span class="ll-stat-title">Observed Queries</span>
                <div class="ll-stat-value text-light">{{ number_format($summary['total_queries']) }}</div>
                <div class="d-flex align-items-center justify-content-between mt-2 pt-2 border-top border-secondary-subtle">
                    <span class="small text-muted">Extra Queries</span>
                    <span class="badge bg-success-subtle text-success ll-badge-pill">0 (Zero Extra)</span>
                </div>
            </div>
        </div>

        <!-- Aggregate Duration -->
        <div class="col-12 col-sm-6 col-xl-4 col-xxl-2">
            <div class="ll-card p-3 h-100">
                <span class="ll-stat-title text-primary">Aggregate Time</span>
                <div class="ll-stat-value text-primary">{{ $summary['total_duration_ms'] }} <span class="fs-6 text-muted">ms</span></div>
                <div class="d-flex align-items-center justify-content-between mt-2 pt-2 border-top border-secondary-subtle">
                    <span class="small text-muted">Total observed</span>
                    <span class="small text-muted">Cumulative</span>
                </div>
            </div>
        </div>

        <!-- Average Duration -->
        <div class="col-12 col-sm-6 col-xl-4 col-xxl-2">
            <div class="ll-card p-3 h-100">
                <span class="ll-stat-title text-info">Avg Query Time</span>
                <div class="ll-stat-value text-info">{{ $summary['avg_duration_ms'] }} <span class="fs-6 text-muted">ms</span></div>
                <div class="d-flex align-items-center justify-content-between mt-2 pt-2 border-top border-secondary-subtle">
                    <span class="small text-muted">Execution speed</span>
                    <span class="badge bg-info-subtle text-info ll-badge-pill">Mean</span>
                </div>
            </div>
        </div>

        <!-- Slow Queries -->
        <div class="col-12 col-sm-6 col-xl-4 col-xxl-2">
            <div class="ll-card p-3 h-100">
                <span class="ll-stat-title text-warning">Slow Queries</span>
                <div class="ll-stat-value text-{{ $summary['slow_query_count'] > 0 ? 'warning' : 'light' }}">
                    {{ number_format($summary['slow_query_count']) }}
                </div>
                <div class="d-flex align-items-center justify-content-between mt-2 pt-2 border-top border-secondary-subtle">
                    <span class="small text-muted">Threshold</span>
                    <span class="badge bg-secondary-subtle text-light ll-badge-pill">{{ $slow_threshold }} ms</span>
                </div>
            </div>
        </div>

        <!-- Potential N+1 Warnings -->
        <div class="col-12 col-sm-6 col-xl-4 col-xxl-2">
            <div class="ll-card p-3 h-100">
                <span class="ll-stat-title text-danger">Potential N+1</span>
                <div class="ll-stat-value text-{{ $summary['n_plus_one_warnings_count'] > 0 ? 'danger' : 'light' }}">
                    {{ number_format($summary['n_plus_one_warnings_count']) }}
                </div>
                <div class="d-flex align-items-center justify-content-between mt-2 pt-2 border-top border-secondary-subtle">
                    <span class="small text-muted">Heuristic pattern</span>
                    <span class="badge bg-{{ $summary['n_plus_one_warnings_count'] > 0 ? 'danger' : 'secondary' }}-subtle text-{{ $summary['n_plus_one_warnings_count'] > 0 ? 'danger' : 'muted' }} ll-badge-pill">
                        {{ $summary['n_plus_one_warnings_count'] > 0 ? 'Detected' : 'None' }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Raw Bindings Status -->
        <div class="col-12 col-sm-6 col-xl-4 col-xxl-2">
            <div class="ll-card p-3 h-100">
                <span class="ll-stat-title">Raw Bindings</span>
                <div class="ll-stat-value text-muted" style="font-size: 1.35rem;">
                    {{ $collect_bindings ? 'Captured' : 'Redacted' }}
                </div>
                <div class="d-flex align-items-center justify-content-between mt-2 pt-2 border-top border-secondary-subtle">
                    <span class="small text-muted">Privacy Safe</span>
                    <span class="badge bg-success-subtle text-success ll-badge-pill">Active</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Potential N+1 Warnings Section -->
    <div class="ll-card p-3 mb-4">
        <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom border-secondary-subtle">
            <div>
                <h6 class="mb-0 fw-bold text-light">
                    <i class="fa-solid fa-triangle-exclamation text-danger me-2"></i> Potential N+1 Query Patterns
                </h6>
                <small class="text-muted">Heuristic detection based on repeated normalized query structures within a single request</small>
            </div>
            <span class="badge bg-danger-subtle text-danger border border-danger-subtle ll-badge-pill">
                {{ count($summary['potential_n_plus_one']) }} Alerts
            </span>
        </div>

        @if(empty($summary['potential_n_plus_one']))
            <div class="p-4 text-center text-muted">
                <i class="fa-regular fa-circle-check fs-2 text-success opacity-50 mb-2"></i>
                <p class="small mb-0">No repeated query patterns detected in recent requests.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-dark table-hover align-middle mb-0" style="background: transparent;">
                    <thead>
                        <tr class="text-muted small text-uppercase" style="border-color: var(--ll-card-border);">
                            <th scope="col" style="width: 90px;">Repeats</th>
                            <th scope="col">Normalized Query Pattern</th>
                            <th scope="col" style="width: 140px;">Route</th>
                            <th scope="col" style="width: 100px;">Duration</th>
                            <th scope="col">Optimization Hint</th>
                        </tr>
                    </thead>
                    <tbody style="border-color: var(--ll-card-border);">
                        @foreach($summary['potential_n_plus_one'] as $n1)
                            <tr>
                                <td>
                                    <span class="badge bg-danger rounded-pill fs-6 px-3 py-1">
                                        {{ $n1['count'] }} &times;
                                    </span>
                                </td>
                                <td>
                                    <pre class="bg-black p-2 rounded text-warning small mb-0 font-monospace text-break"><code>{{ $n1['sample_sql'] ?? $n1['fingerprint'] }}</code></pre>
                                </td>
                                <td class="font-monospace small text-light text-break">
                                    {{ $n1['route'] }}
                                </td>
                                <td class="small text-danger fw-bold">
                                    {{ $n1['total_duration_ms'] }} ms
                                </td>
                                <td class="small text-info">
                                    <i class="fa-regular fa-lightbulb me-1"></i> {{ $n1['hint'] }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <!-- Slow Queries & Server Metrics Note -->
    <div class="row g-4 mb-4">
        <!-- Slow Queries Table -->
        <div class="col-12 col-xl-8">
            <div class="ll-card p-3 h-100">
                <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom border-secondary-subtle">
                    <div>
                        <h6 class="mb-0 fw-bold text-light">Slow Queries Log</h6>
                        <small class="text-muted">Queries exceeding {{ $slow_threshold }} ms duration threshold</small>
                    </div>
                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle ll-badge-pill">
                        {{ count($summary['slow_queries']) }} Slow
                    </span>
                </div>

                @if(empty($summary['slow_queries']))
                    <div class="p-4 text-center text-muted">
                        <i class="fa-regular fa-circle-check fs-2 text-success opacity-50 mb-2"></i>
                        <p class="small mb-0">No slow queries detected!</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-dark table-hover align-middle mb-0" style="background: transparent;">
                            <thead>
                                <tr class="text-muted small text-uppercase" style="border-color: var(--ll-card-border);">
                                    <th scope="col" style="width: 100px;">Duration</th>
                                    <th scope="col" style="width: 90px;">Conn</th>
                                    <th scope="col">Normalized SQL Statement</th>
                                </tr>
                            </thead>
                            <tbody style="border-color: var(--ll-card-border);">
                                @foreach($summary['slow_queries'] as $slowQ)
                                    <tr>
                                        <td class="small text-warning fw-bold">
                                            {{ $slowQ['duration_ms'] }} ms
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary text-uppercase" style="font-size: 0.72rem;">
                                                {{ $slowQ['connection'] }}
                                            </span>
                                        </td>
                                        <td>
                                            <pre class="bg-black p-2 rounded text-light small mb-0 font-monospace text-break"><code>{{ $slowQ['sql'] }}</code></pre>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <!-- Strict Zero-Query Architecture Note -->
        <div class="col-12 col-xl-4">
            <div class="ll-card p-3 h-100">
                <h6 class="fw-bold text-light mb-2">
                    <i class="fa-solid fa-circle-info text-info me-1"></i> Database Server Metrics Limitation
                </h6>
                <p class="small text-muted mb-3">
                    There is an essential architectural distinction between <strong>observing existing application queries</strong> and <strong>collecting internal database-server statistics</strong>:
                </p>
                <div class="small text-light bg-black p-3 rounded mb-3 border border-secondary-subtle">
                    <div class="mb-2">
                        <i class="fa-solid fa-check text-success me-1"></i>
                        <strong>Observed Queries:</strong> Collected passively via Laravel's <code>DB::listen</code>. Zero extra queries executed.
                    </div>
                    <div>
                        <i class="fa-solid fa-xmark text-danger me-1"></i>
                        <strong>Server Health Probes:</strong> Global status (buffer pool hit rate, table locks, thread cache) requires issuing new polling queries (e.g. <code>SHOW STATUS</code>). In strict mode, polling is disabled to eliminate monitoring load.
                    </div>
                </div>
                <div class="small text-muted">
                    To capture server-level metrics without adding application queries, configure an external database exporter (such as Prometheus MySQL Exporter) alongside LogLens.
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
