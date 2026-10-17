@extends('loglens::layouts.app', ['title' => 'HTTP Performance Monitoring'])

@section('content')
<div class="container-fluid p-0">
    <!-- Notice Banner -->
    <div class="alert alert-info border-info-subtle bg-info-subtle text-info-emphasis d-flex align-items-center justify-content-between p-3 mb-4 rounded-3 shadow-sm" role="alert">
        <div class="d-flex align-items-center gap-3">
            <span class="fs-4"><i class="fa-solid fa-bolt-lightning text-info"></i></span>
            <div>
                <strong class="d-block mb-1">HTTP Request Performance Observer</strong>
                <span class="small">
                    Capturing incoming request throughput, latency percentiles, and status distribution in bounded in-memory storage without database overhead.
                </span>
            </div>
        </div>
        <span class="badge bg-{{ $is_enabled ? 'success' : 'secondary' }} text-white d-none d-md-inline-block">
            {{ $is_enabled ? 'Instrumentation Active' : 'Instrumentation Standby' }}
        </span>
    </div>

    <!-- Quick Metric Cards -->
    <div class="row g-3 mb-4">
        <!-- Total Requests -->
        <div class="col-12 col-sm-6 col-xl-4 col-xxl-2">
            <div class="ll-card p-3 h-100">
                <span class="ll-stat-title">Total Requests</span>
                <div class="ll-stat-value text-light">{{ number_format($summary['total_requests']) }}</div>
                <div class="d-flex align-items-center justify-content-between mt-2 pt-2 border-top border-secondary-subtle">
                    <span class="small text-muted">Sample Window</span>
                    <span class="badge bg-secondary-subtle text-light ll-badge-pill">In-Memory</span>
                </div>
            </div>
        </div>

        <!-- Average Latency -->
        <div class="col-12 col-sm-6 col-xl-4 col-xxl-2">
            <div class="ll-card p-3 h-100">
                <span class="ll-stat-title text-info">Avg Latency</span>
                <div class="ll-stat-value text-info">{{ $summary['avg_response_time_ms'] }} <span class="fs-6 text-muted">ms</span></div>
                <div class="d-flex align-items-center justify-content-between mt-2 pt-2 border-top border-secondary-subtle">
                    <span class="small text-muted">Median (p50)</span>
                    <span class="small text-light">{{ $summary['median_response_time_ms'] }} ms</span>
                </div>
            </div>
        </div>

        <!-- p95 Latency -->
        <div class="col-12 col-sm-6 col-xl-4 col-xxl-2">
            <div class="ll-card p-3 h-100">
                <span class="ll-stat-title text-warning">p95 Latency</span>
                <div class="ll-stat-value text-warning">{{ $summary['p95_response_time_ms'] }} <span class="fs-6 text-muted">ms</span></div>
                <div class="d-flex align-items-center justify-content-between mt-2 pt-2 border-top border-secondary-subtle">
                    <span class="small text-muted">95% of users</span>
                    <span class="badge bg-warning-subtle text-warning ll-badge-pill">p95</span>
                </div>
            </div>
        </div>

        <!-- p99 Latency -->
        <div class="col-12 col-sm-6 col-xl-4 col-xxl-2">
            <div class="ll-card p-3 h-100">
                <span class="ll-stat-title text-danger">p99 Latency</span>
                <div class="ll-stat-value text-danger">{{ $summary['p99_response_time_ms'] }} <span class="fs-6 text-muted">ms</span></div>
                <div class="d-flex align-items-center justify-content-between mt-2 pt-2 border-top border-secondary-subtle">
                    <span class="small text-muted">Tail latency</span>
                    <span class="badge bg-danger-subtle text-danger ll-badge-pill">p99</span>
                </div>
            </div>
        </div>

        <!-- Slow Requests -->
        <div class="col-12 col-sm-6 col-xl-4 col-xxl-2">
            <div class="ll-card p-3 h-100">
                <span class="ll-stat-title">Slow Requests</span>
                <div class="ll-stat-value text-{{ $summary['slow_request_count'] > 0 ? 'warning' : 'light' }}">
                    {{ number_format($summary['slow_request_count']) }}
                </div>
                <div class="d-flex align-items-center justify-content-between mt-2 pt-2 border-top border-secondary-subtle">
                    <span class="small text-muted">Threshold</span>
                    <span class="badge bg-secondary-subtle text-light ll-badge-pill">{{ $slow_threshold }} ms</span>
                </div>
            </div>
        </div>

        <!-- 4xx / 5xx Errors -->
        @php
            $errCount = ($summary['status_distribution']['4xx'] ?? 0) + ($summary['status_distribution']['5xx'] ?? 0);
        @endphp
        <div class="col-12 col-sm-6 col-xl-4 col-xxl-2">
            <div class="ll-card p-3 h-100">
                <span class="ll-stat-title text-danger">HTTP Errors</span>
                <div class="ll-stat-value text-{{ $errCount > 0 ? 'danger' : 'success' }}">{{ $errCount }}</div>
                <div class="d-flex align-items-center justify-content-between mt-2 pt-2 border-top border-secondary-subtle">
                    <span class="small text-muted">4xx / 5xx</span>
                    <span class="badge bg-{{ $errCount > 0 ? 'danger' : 'success' }}-subtle text-{{ $errCount > 0 ? 'danger' : 'success' }} ll-badge-pill">
                        {{ $errCount > 0 ? 'Alert' : 'Clean' }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="row g-4 mb-4">
        <!-- Latency Trend -->
        <div class="col-12 col-xl-8">
            <div class="ll-card p-3 h-100">
                <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom border-secondary-subtle">
                    <div>
                        <h6 class="mb-0 fw-bold text-light">Response Time Distribution (Recent Requests)</h6>
                        <small class="text-muted">Recorded request durations in milliseconds</small>
                    </div>
                </div>
                <div class="ll-chart-box" style="height: 240px;">
                    <canvas id="httpLatencyChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Status Distribution -->
        <div class="col-12 col-xl-4">
            <div class="ll-card p-3 h-100">
                <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom border-secondary-subtle">
                    <div>
                        <h6 class="mb-0 fw-bold text-light">Status Code Proportion</h6>
                        <small class="text-muted">Breakdown by HTTP response code</small>
                    </div>
                </div>
                <div class="ll-chart-box d-flex align-items-center justify-content-center" style="height: 240px;">
                    <canvas id="statusDoughnutChart" style="max-height: 220px;"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Requests & Slow Requests Tables -->
    <div class="row g-4">
        <!-- Recent Requests -->
        <div class="col-12 col-xl-7">
            <div class="ll-card p-3">
                <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom border-secondary-subtle">
                    <div>
                        <h6 class="mb-0 fw-bold text-light">Recent Observed Requests</h6>
                        <small class="text-muted">Latest HTTP transactions</small>
                    </div>
                    <span class="badge bg-secondary text-uppercase ll-badge-pill">
                        {{ count($summary['recent_requests']) }} Requests
                    </span>
                </div>

                @if(empty($summary['recent_requests']))
                    <div class="p-4 text-center text-muted">
                        <i class="fa-solid fa-bolt-lightning fs-2 text-secondary opacity-50 mb-2"></i>
                        <p class="small mb-0">No incoming HTTP requests observed yet in this session.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-dark table-hover align-middle mb-0" style="background: transparent;">
                            <thead>
                                <tr class="text-muted small text-uppercase" style="border-color: var(--ll-card-border);">
                                    <th scope="col" style="width: 80px;">Status</th>
                                    <th scope="col">Route / Path</th>
                                    <th scope="col" style="width: 100px;">Duration</th>
                                    <th scope="col" class="text-end" style="width: 110px;">Correlation</th>
                                </tr>
                            </thead>
                            <tbody style="border-color: var(--ll-card-border);">
                                @foreach($summary['recent_requests'] as $req)
                                    @php
                                        $sc = (int) ($req['status_code'] ?? 200);
                                        $statusBadge = match(true) {
                                            $sc >= 200 && $sc < 300 => 'success',
                                            $sc >= 300 && $sc < 400 => 'info',
                                            $sc >= 400 && $sc < 500 => 'warning',
                                            default => 'danger',
                                        };
                                    @endphp
                                    <tr>
                                        <td>
                                            <span class="badge bg-{{ $statusBadge }} rounded-pill" style="font-size: 0.75rem;">
                                                {{ $sc }}
                                            </span>
                                        </td>
                                        <td class="font-monospace small text-light text-break">
                                            {{ $req['route'] }}
                                        </td>
                                        <td class="small {{ !empty($req['is_slow']) ? 'text-warning fw-bold' : 'text-muted' }}">
                                            {{ $req['duration_ms'] }} ms
                                        </td>
                                        <td class="text-end font-monospace text-muted small" style="font-size: 0.72rem;">
                                            {{ substr($req['correlation_id'] ?? '', 0, 8) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <!-- Slow Requests -->
        <div class="col-12 col-xl-5">
            <div class="ll-card p-3">
                <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom border-secondary-subtle">
                    <div>
                        <h6 class="mb-0 fw-bold text-light">Slow Requests</h6>
                        <small class="text-muted">Exceeded threshold ({{ $slow_threshold }} ms)</small>
                    </div>
                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle ll-badge-pill">
                        {{ count($summary['recent_slow_requests']) }} Slow
                    </span>
                </div>

                @if(empty($summary['recent_slow_requests']))
                    <div class="p-4 text-center text-muted">
                        <i class="fa-regular fa-circle-check fs-2 text-success opacity-50 mb-2"></i>
                        <p class="small mb-0">No slow requests detected!</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-dark table-hover align-middle mb-0" style="background: transparent;">
                            <thead>
                                <tr class="text-muted small text-uppercase" style="border-color: var(--ll-card-border);">
                                    <th scope="col">Route</th>
                                    <th scope="col" class="text-end">Latency</th>
                                </tr>
                            </thead>
                            <tbody style="border-color: var(--ll-card-border);">
                                @foreach($summary['recent_slow_requests'] as $slowReq)
                                    <tr>
                                        <td class="font-monospace small text-light text-break">
                                            {{ $slowReq['route'] }}
                                        </td>
                                        <td class="text-end text-warning fw-bold small">
                                            {{ $slowReq['duration_ms'] }} ms
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const recentRequests = {!! json_encode($summary['recent_requests']) !!};
    const statusData = {!! json_encode($summary['status_distribution']) !!};

    // 1. Latency Trend Chart
    const latencyCtx = document.getElementById('httpLatencyChart');
    if (latencyCtx) {
        const labels = recentRequests.slice(0, 30).reverse().map((r, i) => `#${i + 1}`);
        const dataPoints = recentRequests.slice(0, 30).reverse().map(r => r.duration_ms);

        new Chart(latencyCtx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Latency (ms)',
                    data: dataPoints,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.15)',
                    tension: 0.3,
                    fill: true,
                    pointBackgroundColor: '#3b82f6',
                    pointRadius: 3,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        ticks: { color: '#94a3b8', font: { size: 10 } },
                        grid: { color: 'rgba(255, 255, 255, 0.05)' }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { color: '#94a3b8' },
                        grid: { color: 'rgba(255, 255, 255, 0.05)' }
                    }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }

    // 2. Status Distribution Chart
    const statusCtx = document.getElementById('statusDoughnutChart');
    if (statusCtx) {
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['2xx Success', '3xx Redirect', '4xx Client Err', '5xx Server Err'],
                datasets: [{
                    data: [
                        statusData['2xx'] || 0,
                        statusData['3xx'] || 0,
                        statusData['4xx'] || 0,
                        statusData['5xx'] || 0,
                    ],
                    backgroundColor: ['#10b981', '#06b6d4', '#f59e0b', '#ef4444'],
                    borderWidth: 2,
                    borderColor: '#1e293b'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: { color: '#cbd5e1', boxWidth: 12, font: { size: 10 } }
                    }
                }
            }
        });
    }
});
</script>
@endpush
