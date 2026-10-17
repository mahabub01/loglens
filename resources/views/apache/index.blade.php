@extends('loglens::layouts.app', ['title' => 'Apache Server Status'])

@section('content')
<div class="container-fluid p-0">
    <!-- Notice Banner -->
    <div class="alert alert-{{ $status['state'] === 'active' ? 'success' : ($status['state'] === 'stale' ? 'warning' : 'secondary') }} border-secondary-subtle d-flex align-items-center justify-content-between p-3 mb-4 rounded-3 shadow-sm" role="alert">
        <div class="d-flex align-items-center gap-3">
            <span class="fs-4">
                <i class="fa-solid fa-server text-{{ $status['state'] === 'active' ? 'success' : ($status['state'] === 'stale' ? 'warning' : 'danger') }}"></i>
            </span>
            <div>
                <strong class="d-block mb-1">
                    Apache mod_status Live Monitor
                    @if(!empty($latest['server_version']))
                        <span class="badge bg-dark border border-secondary-subtle text-light ms-2 fw-normal">{{ $latest['server_version'] }}</span>
                    @endif
                </strong>
                <span class="small text-muted">
                    Shared 1-second background daemon collection via <code>mod_status?auto</code>. Dashboard visitors read cached snapshots with <strong>zero direct Apache polling</strong>.
                </span>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            @if(! $is_enabled)
                <span class="badge bg-secondary text-white" id="collectorStateBadge">Integration Disabled</span>
            @elseif($status['state'] === 'active')
                <span class="badge bg-success text-white" id="collectorStateBadge"><i class="fa-solid fa-circle-check me-1"></i> Daemon Active</span>
            @elseif($status['state'] === 'stale')
                <span class="badge bg-warning text-dark" id="collectorStateBadge"><i class="fa-solid fa-triangle-exclamation me-1"></i> Metrics Stale</span>
            @elseif($status['state'] === 'error')
                <span class="badge bg-danger text-white" id="collectorStateBadge"><i class="fa-solid fa-circle-xmark me-1"></i> Collector Error</span>
            @else
                <span class="badge bg-secondary text-white" id="collectorStateBadge">Waiting for Daemon</span>
            @endif
            <button class="btn btn-sm btn-outline-light" onclick="fetchApacheMetrics()" title="Refresh metrics">
                <i class="fa-solid fa-rotate" id="refreshIcon"></i>
            </button>
        </div>
    </div>

    <!-- Error Alert if present -->
    @if(!empty($status['last_error']))
    <div class="alert alert-danger bg-danger-subtle text-danger-emphasis border-danger-subtle p-3 mb-4 rounded-3 d-flex align-items-center gap-3" role="alert">
        <i class="fa-solid fa-circle-exclamation fs-4 text-danger"></i>
        <div>
            <strong>Collector Reported Error:</strong>
            <span class="d-block small">{{ $status['last_error'] }}</span>
        </div>
    </div>
    @endif

    <!-- Stale Warning Banner -->
    <div class="alert alert-warning bg-warning-subtle text-warning-emphasis border-warning-subtle p-3 mb-4 rounded-3 d-flex align-items-center justify-content-between {{ $is_stale && $is_enabled ? '' : 'd-none' }}" id="staleAlertBanner" role="alert">
        <div class="d-flex align-items-center gap-3">
            <i class="fa-solid fa-clock-rotate-left fs-4 text-warning"></i>
            <div>
                <strong>Metrics are Stale:</strong>
                <span class="d-block small">The background collector daemon has not written a recent snapshot. Verify that <code>php artisan loglens:apache-collect</code> is running under your process supervisor.</span>
            </div>
        </div>
        <span class="badge bg-warning text-dark">Age &gt; {{ $status['stale_threshold_seconds'] ?? 5 }}s</span>
    </div>

    <!-- Quick Metric Cards -->
    <div class="row g-3 mb-4">
        <!-- Requests Per Second -->
        <div class="col-12 col-sm-6 col-xl-4 col-xxl-2">
            <div class="ll-card p-3 h-100">
                <span class="ll-stat-title text-info">Requests / Sec</span>
                <div class="ll-stat-value text-info" id="valRps">
                    @if(isset($latest['rps']) && $latest['rps'] !== null)
                        {{ number_format($latest['rps'], 1) }}
                    @elseif(isset($latest['server_req_per_sec']) && $latest['server_req_per_sec'] !== null)
                        {{ number_format($latest['server_req_per_sec'], 1) }} <small class="fs-6 text-muted">(avg)</small>
                    @else
                        <span class="text-muted">N/A</span>
                    @endif
                </div>
                <div class="d-flex align-items-center justify-content-between mt-2 pt-2 border-top border-secondary-subtle">
                    <span class="small text-muted">Instant Rate</span>
                    <span class="badge bg-info-subtle text-info ll-badge-pill">RPS</span>
                </div>
            </div>
        </div>

        <!-- Transferred Bytes Per Second -->
        <div class="col-12 col-sm-6 col-xl-4 col-xxl-2">
            <div class="ll-card p-3 h-100">
                <span class="ll-stat-title text-primary">Throughput</span>
                <div class="ll-stat-value text-primary" id="valBps">
                    @if(isset($latest['bps']) && $latest['bps'] !== null)
                        @if($latest['bps'] >= 1048576)
                            {{ round($latest['bps'] / 1048576, 2) }} <span class="fs-6 text-muted">MB/s</span>
                        @elseif($latest['bps'] >= 1024)
                            {{ round($latest['bps'] / 1024, 1) }} <span class="fs-6 text-muted">KB/s</span>
                        @else
                            {{ round($latest['bps'], 0) }} <span class="fs-6 text-muted">B/s</span>
                        @endif
                    @elseif(isset($latest['server_bytes_per_sec']) && $latest['server_bytes_per_sec'] !== null)
                        @if($latest['server_bytes_per_sec'] >= 1048576)
                            {{ round($latest['server_bytes_per_sec'] / 1048576, 2) }} <span class="fs-6 text-muted">MB/s (avg)</span>
                        @elseif($latest['server_bytes_per_sec'] >= 1024)
                            {{ round($latest['server_bytes_per_sec'] / 1024, 1) }} <span class="fs-6 text-muted">KB/s (avg)</span>
                        @else
                            {{ round($latest['server_bytes_per_sec'], 0) }} <span class="fs-6 text-muted">B/s (avg)</span>
                        @endif
                    @else
                        <span class="text-muted">N/A</span>
                    @endif
                </div>
                <div class="d-flex align-items-center justify-content-between mt-2 pt-2 border-top border-secondary-subtle">
                    <span class="small text-muted">Data Transfer</span>
                    <span class="badge bg-primary-subtle text-primary ll-badge-pill">BPS</span>
                </div>
            </div>
        </div>

        <!-- Busy Workers -->
        <div class="col-12 col-sm-6 col-xl-4 col-xxl-2">
            <div class="ll-card p-3 h-100">
                <span class="ll-stat-title text-warning">Busy Workers</span>
                <div class="ll-stat-value text-warning" id="valBusyWorkers">
                    {{ $latest['busy_workers'] ?? 'N/A' }}
                </div>
                <div class="d-flex align-items-center justify-content-between mt-2 pt-2 border-top border-secondary-subtle">
                    <span class="small text-muted">Active Processing</span>
                    <span class="badge bg-warning-subtle text-warning ll-badge-pill">Busy</span>
                </div>
            </div>
        </div>

        <!-- Idle Workers -->
        <div class="col-12 col-sm-6 col-xl-4 col-xxl-2">
            <div class="ll-card p-3 h-100">
                <span class="ll-stat-title text-success">Idle Workers</span>
                <div class="ll-stat-value text-success" id="valIdleWorkers">
                    {{ $latest['idle_workers'] ?? 'N/A' }}
                </div>
                <div class="d-flex align-items-center justify-content-between mt-2 pt-2 border-top border-secondary-subtle">
                    <span class="small text-muted">Available Capacity</span>
                    <span class="badge bg-success-subtle text-success ll-badge-pill">Idle</span>
                </div>
            </div>
        </div>

        <!-- Total Accesses -->
        <div class="col-12 col-sm-6 col-xl-4 col-xxl-2">
            <div class="ll-card p-3 h-100">
                <span class="ll-stat-title">Total Accesses</span>
                <div class="ll-stat-value text-light" id="valTotalAccesses">
                    {{ isset($latest['total_accesses']) ? number_format($latest['total_accesses']) : 'N/A' }}
                </div>
                <div class="d-flex align-items-center justify-content-between mt-2 pt-2 border-top border-secondary-subtle">
                    <span class="small text-muted">Lifetime Requests</span>
                    <span class="badge bg-secondary-subtle text-light ll-badge-pill">Hits</span>
                </div>
            </div>
        </div>

        <!-- Server Uptime -->
        @php
            $uptimeSec = $latest['uptime_seconds'] ?? null;
            $uptimeFormatted = 'N/A';
            if ($uptimeSec !== null) {
                $days = floor($uptimeSec / 86400);
                $hours = floor(($uptimeSec % 86400) / 3600);
                $mins = floor(($uptimeSec % 3600) / 60);
                $secs = $uptimeSec % 60;
                $uptimeFormatted = ($days > 0 ? "{$days}d " : '') . "{$hours}h {$mins}m";
            }
        @endphp
        <div class="col-12 col-sm-6 col-xl-4 col-xxl-2">
            <div class="ll-card p-3 h-100">
                <span class="ll-stat-title">Server Uptime</span>
                <div class="ll-stat-value text-light" id="valUptime">
                    {{ $uptimeFormatted }}
                </div>
                <div class="d-flex align-items-center justify-content-between mt-2 pt-2 border-top border-secondary-subtle">
                    <span class="small text-muted">Last Update</span>
                    <span class="small text-muted" id="valLastUpdated">
                        {{ !empty($latest['collected_at']) ? substr($latest['collected_at'], 11) : 'Waiting' }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="row g-4 mb-4">
        <!-- RPS & Throughput Trend Chart -->
        <div class="col-12 col-xl-7">
            <div class="ll-card p-3 h-100">
                <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom border-secondary-subtle">
                    <div>
                        <h6 class="mb-0 fw-bold text-light">Throughput History (RPS & BPS)</h6>
                        <small class="text-muted">Real-time differential traffic rates calculated from consecutive samples</small>
                    </div>
                    <span class="badge bg-info-subtle text-info border border-info-subtle ll-badge-pill">
                        1s Sampling
                    </span>
                </div>
                <div class="ll-chart-box" style="height: 280px;">
                    <canvas id="apacheThroughputChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Worker Utilization Chart -->
        <div class="col-12 col-xl-5">
            <div class="ll-card p-3 h-100">
                <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom border-secondary-subtle">
                    <div>
                        <h6 class="mb-0 fw-bold text-light">Worker Utilization</h6>
                        <small class="text-muted">Busy vs Idle Apache worker processes</small>
                    </div>
                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle ll-badge-pill">
                        Worker Capacity
                    </span>
                </div>
                <div class="ll-chart-box" style="height: 280px;">
                    <canvas id="apacheWorkersChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Scoreboard Visualization -->
    @php
        $sb = $latest['scoreboard_breakdown'] ?? [];
        $sbRaw = $latest['scoreboard_raw'] ?? '';
    @endphp
    <div class="ll-card p-3 mb-4">
        <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom border-secondary-subtle">
            <div>
                <h6 class="mb-0 fw-bold text-light">
                    <i class="fa-solid fa-table-cells text-primary me-2"></i> Worker Scoreboard Distribution
                </h6>
                <small class="text-muted">Detailed allocation of Apache process worker slots across execution phases</small>
            </div>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle ll-badge-pill" id="valTotalSlots">
                {{ strlen($sbRaw) }} Total Slots
            </span>
        </div>

        <!-- Scoreboard Stat Pills -->
        <div class="row g-2 mb-3" id="scoreboardPillsContainer">
            <div class="col-6 col-md-4 col-lg-2">
                <div class="p-2 rounded bg-dark border border-secondary-subtle d-flex align-items-center justify-content-between">
                    <div>
                        <span class="badge bg-primary me-1">W</span>
                        <span class="small text-muted">Sending Reply</span>
                    </div>
                    <strong class="text-light" id="sbCountWriting">{{ $sb['writing'] ?? 0 }}</strong>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <div class="p-2 rounded bg-dark border border-secondary-subtle d-flex align-items-center justify-content-between">
                    <div>
                        <span class="badge bg-warning text-dark me-1">R</span>
                        <span class="small text-muted">Reading Req</span>
                    </div>
                    <strong class="text-light" id="sbCountReading">{{ $sb['reading'] ?? 0 }}</strong>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <div class="p-2 rounded bg-dark border border-secondary-subtle d-flex align-items-center justify-content-between">
                    <div>
                        <span class="badge bg-info text-dark me-1">K</span>
                        <span class="small text-muted">Keepalive</span>
                    </div>
                    <strong class="text-light" id="sbCountKeepalive">{{ $sb['keepalive'] ?? 0 }}</strong>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <div class="p-2 rounded bg-dark border border-secondary-subtle d-flex align-items-center justify-content-between">
                    <div>
                        <span class="badge bg-secondary me-1">_</span>
                        <span class="small text-muted">Waiting (Idle)</span>
                    </div>
                    <strong class="text-light" id="sbCountWaiting">{{ $sb['waiting'] ?? 0 }}</strong>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <div class="p-2 rounded bg-dark border border-secondary-subtle d-flex align-items-center justify-content-between">
                    <div>
                        <span class="badge bg-danger me-1">C/L</span>
                        <span class="small text-muted">Close/Log</span>
                    </div>
                    <strong class="text-light" id="sbCountClosingLogging">{{ ($sb['closing'] ?? 0) + ($sb['logging'] ?? 0) }}</strong>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <div class="p-2 rounded bg-dark border border-secondary-subtle d-flex align-items-center justify-content-between">
                    <div>
                        <span class="badge bg-dark border border-secondary me-1">.</span>
                        <span class="small text-muted">Open Slots</span>
                    </div>
                    <strong class="text-light" id="sbCountOpen">{{ $sb['open_slot'] ?? 0 }}</strong>
                </div>
            </div>
        </div>

        <!-- Raw Scoreboard Visual Map -->
        <div class="bg-black p-3 rounded border border-secondary-subtle overflow-x-auto">
            <span class="small text-muted d-block mb-1">Scoreboard Buffer:</span>
            <code class="text-break font-monospace small" id="scoreboardRawText" style="letter-spacing: 2px; color: #38bdf8;">
                {{ $sbRaw !== '' ? $sbRaw : 'Scoreboard snapshot not available yet.' }}
            </code>
        </div>
    </div>

    <!-- Daemon Configuration & Supervision Architecture Guide -->
    <div class="ll-card p-4">
        <h6 class="fw-bold text-light mb-3">
            <i class="fa-solid fa-book-open text-primary me-2"></i> Shared Daemon Collection Architecture
        </h6>
        <div class="row g-4">
            <div class="col-12 col-lg-6">
                <h6 class="text-info small fw-bold text-uppercase mb-2">How 1-Second Sampling Works</h6>
                <p class="small text-muted mb-2">
                    To eliminate redundant server load, LogLens separates metric collection from web dashboard visitors:
                </p>
                <ul class="small text-muted ps-3 mb-3">
                    <li><strong>Daemon Process:</strong> A background CLI worker (<code>php artisan loglens:apache-collect</code>) polls Apache <code>mod_status?auto</code> once per second.</li>
                    <li><strong>Shared Cache Store:</strong> Snapshots and differential rates are stored in Redis or the application cache.</li>
                    <li><strong>Web Dashboard:</strong> Browser clients poll <code>/loglens/api/metrics/apache</code>, reading strictly from cache with zero direct Apache requests.</li>
                </ul>
            </div>
            <div class="col-12 col-lg-6">
                <h6 class="text-info small fw-bold text-uppercase mb-2">Supervisord Service Configuration</h6>
                <pre class="bg-black text-light p-3 rounded border border-secondary-subtle small mb-0 font-monospace"><code>[program:loglens-apache]
command=php artisan loglens:apache-collect --interval=1
directory=/var/www/html
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/loglens-apache.log</code></pre>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const historyData = @json($history);
        const pollIntervalMs = {{ $poll_interval_ms ?? 2000 }};

        // Initialize Charts
        const throughputCtx = document.getElementById('apacheThroughputChart').getContext('2d');
        const workersCtx = document.getElementById('apacheWorkersChart').getContext('2d');

        const labels = historyData.map(d => d.collected_at ? d.collected_at.substr(11) : '');
        const rpsValues = historyData.map(d => d.rps !== null ? d.rps : (d.server_req_per_sec || 0));
        const bpsValues = historyData.map(d => d.bps !== null ? (d.bps / 1024) : 0); // KB/s
        const busyValues = historyData.map(d => d.busy_workers || 0);
        const idleValues = historyData.map(d => d.idle_workers || 0);

        const throughputChart = new Chart(throughputCtx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Requests / Sec (RPS)',
                        data: rpsValues,
                        borderColor: '#06b6d4',
                        backgroundColor: 'rgba(6, 182, 212, 0.1)',
                        tension: 0.3,
                        fill: true,
                        yAxisID: 'y',
                    },
                    {
                        label: 'Throughput (KB/s)',
                        data: bpsValues,
                        borderColor: '#3b82f6',
                        backgroundColor: 'transparent',
                        borderDash: [4, 4],
                        tension: 0.3,
                        yAxisID: 'y1',
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    x: { grid: { color: 'rgba(255, 255, 255, 0.05)' }, ticks: { color: '#94a3b8', maxTicksLimit: 10 } },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: { display: true, text: 'RPS', color: '#06b6d4' },
                        grid: { color: 'rgba(255, 255, 255, 0.05)' },
                        ticks: { color: '#94a3b8' }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: { display: true, text: 'KB/s', color: '#3b82f6' },
                        grid: { drawOnChartArea: false },
                        ticks: { color: '#94a3b8' }
                    }
                },
                plugins: { legend: { labels: { color: '#e2e8f0' } } }
            }
        });

        const workersChart = new Chart(workersCtx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Busy Workers',
                        data: busyValues,
                        borderColor: '#f59e0b',
                        backgroundColor: 'rgba(245, 158, 11, 0.2)',
                        tension: 0.3,
                        fill: true,
                    },
                    {
                        label: 'Idle Workers',
                        data: idleValues,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        tension: 0.3,
                        fill: true,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    x: { grid: { color: 'rgba(255, 255, 255, 0.05)' }, ticks: { color: '#94a3b8', maxTicksLimit: 10 } },
                    y: {
                        grid: { color: 'rgba(255, 255, 255, 0.05)' },
                        ticks: { color: '#94a3b8', precision: 0 },
                        title: { display: true, text: 'Worker Count', color: '#e2e8f0' }
                    }
                },
                plugins: { legend: { labels: { color: '#e2e8f0' } } }
            }
        });

        // Periodic Live Polling from Shared JSON API (reads cache, does NOT poll Apache)
        window.fetchApacheMetrics = function () {
            const icon = document.getElementById('refreshIcon');
            if (icon) icon.classList.add('fa-spin');

            fetch("{{ route('loglens.api.metrics.apache') }}")
                .then(res => res.json())
                .then(data => {
                    if (!data.success) return;

                    const latest = data.latest;
                    const status = data.status;

                    // Update Status Badge
                    const badge = document.getElementById('collectorStateBadge');
                    const staleBanner = document.getElementById('staleAlertBanner');

                    if (badge) {
                        if (!status.enabled) {
                            badge.className = 'badge bg-secondary text-white';
                            badge.innerText = 'Integration Disabled';
                        } else if (status.state === 'active') {
                            badge.className = 'badge bg-success text-white';
                            badge.innerHTML = '<i class="fa-solid fa-circle-check me-1"></i> Daemon Active';
                        } else if (status.state === 'stale') {
                            badge.className = 'badge bg-warning text-dark';
                            badge.innerHTML = '<i class="fa-solid fa-triangle-exclamation me-1"></i> Metrics Stale';
                        } else if (status.state === 'error') {
                            badge.className = 'badge bg-danger text-white';
                            badge.innerHTML = '<i class="fa-solid fa-circle-xmark me-1"></i> Collector Error';
                        }
                    }

                    if (staleBanner) {
                        if (status.is_stale && status.enabled) {
                            staleBanner.classList.remove('d-none');
                        } else {
                            staleBanner.classList.add('d-none');
                        }
                    }

                    if (!latest) return;

                    // Update KPI Cards
                    if (document.getElementById('valRps')) {
                        const rps = latest.rps !== null ? Number(latest.rps).toFixed(1) : (latest.server_req_per_sec ? Number(latest.server_req_per_sec).toFixed(1) + ' (avg)' : 'N/A');
                        document.getElementById('valRps').innerText = rps;
                    }

                    if (document.getElementById('valBps')) {
                        const bps = latest.bps !== null ? latest.bps : (latest.server_bytes_per_sec || 0);
                        let bpsStr = 'N/A';
                        if (bps >= 1048576) bpsStr = (bps / 1048576).toFixed(2) + ' MB/s';
                        else if (bps >= 1024) bpsStr = (bps / 1024).toFixed(1) + ' KB/s';
                        else if (bps > 0) bpsStr = bps.toFixed(0) + ' B/s';
                        document.getElementById('valBps').innerText = bpsStr;
                    }

                    if (document.getElementById('valBusyWorkers')) {
                        document.getElementById('valBusyWorkers').innerText = latest.busy_workers !== null ? latest.busy_workers : 'N/A';
                    }

                    if (document.getElementById('valIdleWorkers')) {
                        document.getElementById('valIdleWorkers').innerText = latest.idle_workers !== null ? latest.idle_workers : 'N/A';
                    }

                    if (document.getElementById('valTotalAccesses') && latest.total_accesses !== null) {
                        document.getElementById('valTotalAccesses').innerText = Number(latest.total_accesses).toLocaleString();
                    }

                    if (document.getElementById('valLastUpdated') && latest.collected_at) {
                        document.getElementById('valLastUpdated').innerText = latest.collected_at.substr(11);
                    }

                    // Update Scoreboard Values
                    const sb = latest.scoreboard_breakdown || {};
                    if (document.getElementById('sbCountWriting')) document.getElementById('sbCountWriting').innerText = sb.writing || 0;
                    if (document.getElementById('sbCountReading')) document.getElementById('sbCountReading').innerText = sb.reading || 0;
                    if (document.getElementById('sbCountKeepalive')) document.getElementById('sbCountKeepalive').innerText = sb.keepalive || 0;
                    if (document.getElementById('sbCountWaiting')) document.getElementById('sbCountWaiting').innerText = sb.waiting || 0;
                    if (document.getElementById('sbCountClosingLogging')) document.getElementById('sbCountClosingLogging').innerText = (sb.closing || 0) + (sb.logging || 0);
                    if (document.getElementById('sbCountOpen')) document.getElementById('sbCountOpen').innerText = sb.open_slot || 0;

                    if (document.getElementById('scoreboardRawText') && latest.scoreboard_raw) {
                        document.getElementById('scoreboardRawText').innerText = latest.scoreboard_raw;
                        document.getElementById('valTotalSlots').innerText = latest.scoreboard_raw.length + ' Total Slots';
                    }

                    // Update History Charts if history returned
                    if (data.history && data.history.length > 0) {
                        const h = data.history;
                        throughputChart.data.labels = h.map(d => d.collected_at ? d.collected_at.substr(11) : '');
                        throughputChart.data.datasets[0].data = h.map(d => d.rps !== null ? d.rps : (d.server_req_per_sec || 0));
                        throughputChart.data.datasets[1].data = h.map(d => d.bps !== null ? (d.bps / 1024) : 0);
                        throughputChart.update('none');

                        workersChart.data.labels = h.map(d => d.collected_at ? d.collected_at.substr(11) : '');
                        workersChart.data.datasets[0].data = h.map(d => d.busy_workers || 0);
                        workersChart.data.datasets[1].data = h.map(d => d.idle_workers || 0);
                        workersChart.update('none');
                    }
                })
                .catch(err => console.error('Error fetching Apache metrics:', err))
                .finally(() => {
                    if (icon) icon.classList.remove('fa-spin');
                });
        };

        // Start live polling loop
        setInterval(window.fetchApacheMetrics, pollIntervalMs);
    });
</script>
@endpush
