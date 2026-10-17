@extends('loglens::layouts.app', ['title' => 'System Overview'])

@section('content')
<div class="container-fluid p-0">
    <!-- Notice Banner: Phase 1 Package Foundation -->
    <div class="alert alert-info border-info-subtle bg-info-subtle text-info-emphasis d-flex align-items-center justify-content-between p-3 mb-4 rounded-3 shadow-sm" role="alert">
        <div class="d-flex align-items-center gap-3">
            <span class="fs-4"><i class="fa-solid fa-circle-info"></i></span>
            <div>
                <strong class="d-block mb-1">LogLens Active &bull; Phase 2: Core Log Analyzer Ready</strong>
                <span class="small">
                    Phase 1: Package Foundation Active. Log file browsing, Monolog parser, live tail, error grouping and safe cleanup are now available.
                </span>
            </div>
        </div>
        <span class="badge bg-info text-dark d-none d-md-inline-block">Strict DB Mode Active</span>
    </div>

    <!-- Overview Metric Cards (Explicit Placeholder / Disabled States) -->
    <div class="row g-3 mb-4">
        <!-- Card 1: App Health -->
        <div class="col-12 col-sm-6 col-xl-4 col-xxl-2">
            <div class="ll-card p-3 h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <span class="ll-stat-title">App Health</span>
                    <i class="fa-solid fa-heart-pulse text-success fs-5"></i>
                </div>
                <div class="ll-stat-value text-success">Healthy</div>
                <div class="d-flex align-items-center justify-content-between mt-2 pt-2 border-top border-secondary-subtle">
                    <span class="small text-muted">Core Framework</span>
                    <span class="badge bg-success-subtle text-success ll-badge-pill">Standby</span>
                </div>
            </div>
        </div>

        <!-- Card 2: HTTP Rate -->
        <div class="col-12 col-sm-6 col-xl-4 col-xxl-2">
            <div class="ll-card p-3 h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <span class="ll-stat-title">HTTP Requests</span>
                    <i class="fa-solid fa-bolt-lightning text-info fs-5"></i>
                </div>
                <div class="ll-stat-value text-muted">N/A</div>
                <div class="d-flex align-items-center justify-content-between mt-2 pt-2 border-top border-secondary-subtle">
                    <span class="small text-muted">Collector</span>
                    <span class="badge bg-secondary-subtle text-muted ll-badge-pill">Disabled</span>
                </div>
            </div>
        </div>

        <!-- Card 3: Avg Response Time -->
        <div class="col-12 col-sm-6 col-xl-4 col-xxl-2">
            <div class="ll-card p-3 h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <span class="ll-stat-title">Avg Latency</span>
                    <i class="fa-solid fa-stopwatch text-warning fs-5"></i>
                </div>
                <div class="ll-stat-value text-muted">N/A</div>
                <div class="d-flex align-items-center justify-content-between mt-2 pt-2 border-top border-secondary-subtle">
                    <span class="small text-muted">Threshold</span>
                    <span class="badge bg-secondary-subtle text-muted ll-badge-pill">1,000 ms</span>
                </div>
            </div>
        </div>

        <!-- Card 4: Observed DB Queries -->
        <div class="col-12 col-sm-6 col-xl-4 col-xxl-2">
            <div class="ll-card p-3 h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <span class="ll-stat-title">DB Queries</span>
                    <i class="fa-solid fa-database text-primary fs-5"></i>
                </div>
                <div class="ll-stat-value text-muted">N/A</div>
                <div class="d-flex align-items-center justify-content-between mt-2 pt-2 border-top border-secondary-subtle">
                    <span class="small text-muted">Extra Queries</span>
                    <span class="badge bg-success-subtle text-success ll-badge-pill">0 (Zero Extra)</span>
                </div>
            </div>
        </div>

        <!-- Card 5: Slow Queries -->
        <div class="col-12 col-sm-6 col-xl-4 col-xxl-2">
            <div class="ll-card p-3 h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <span class="ll-stat-title">Slow Queries</span>
                    <i class="fa-solid fa-triangle-exclamation text-danger fs-5"></i>
                </div>
                <div class="ll-stat-value text-muted">N/A</div>
                <div class="d-flex align-items-center justify-content-between mt-2 pt-2 border-top border-secondary-subtle">
                    <span class="small text-muted">Threshold</span>
                    <span class="badge bg-secondary-subtle text-muted ll-badge-pill">500 ms</span>
                </div>
            </div>
        </div>

        <!-- Card 6: Apache Workers -->
        <div class="col-12 col-sm-6 col-xl-4 col-xxl-2">
            <div class="ll-card p-3 h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <span class="ll-stat-title">Apache Workers</span>
                    <i class="fa-solid fa-server text-secondary fs-5"></i>
                </div>
                <div class="ll-stat-value text-muted">N/A</div>
                <div class="d-flex align-items-center justify-content-between mt-2 pt-2 border-top border-secondary-subtle">
                    <span class="small text-muted">mod_status</span>
                    <span class="badge bg-secondary-subtle text-muted ll-badge-pill">Standby</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart Integration Points (Bootstrap 5 + Chart.js Integration) -->
    <div class="row g-4 mb-4">
        <!-- Chart 1: HTTP Throughput & Latency -->
        <div class="col-12 col-xl-8">
            <div class="ll-card p-3 h-100">
                <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom border-secondary-subtle">
                    <div>
                        <h6 class="mb-0 fw-bold text-light">HTTP Performance Trend</h6>
                        <small class="text-muted">Requests/sec and Latency distribution (Chart.js Container)</small>
                    </div>
                    <span class="badge bg-secondary text-uppercase ll-badge-pill">Awaiting Collector</span>
                </div>

                <div class="ll-chart-box d-flex align-items-center justify-content-center">
                    <canvas id="httpPerformanceChart" class="w-100" style="max-height: 250px;"></canvas>
                    <div class="ll-placeholder-overlay text-center p-4">
                        <i class="fa-solid fa-chart-line fs-2 text-primary mb-2 opacity-50"></i>
                        <h6 class="text-light mb-1">HTTP Metrics Collector Not Active</h6>
                        <p class="text-muted small mb-0">
                            HTTP throughput and latency streaming will be displayed here upon Phase 3 activation.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chart 2: Query & Error Breakdown -->
        <div class="col-12 col-xl-4">
            <div class="ll-card p-3 h-100">
                <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom border-secondary-subtle">
                    <div>
                        <h6 class="mb-0 fw-bold text-light">Query Breakdown</h6>
                        <small class="text-muted">Observed database query duration</small>
                    </div>
                    <span class="badge bg-secondary text-uppercase ll-badge-pill">Zero Query Mode</span>
                </div>

                <div class="ll-chart-box d-flex align-items-center justify-content-center">
                    <canvas id="queryDistributionChart" class="w-100" style="max-height: 250px;"></canvas>
                    <div class="ll-placeholder-overlay text-center p-4">
                        <i class="fa-solid fa-chart-pie fs-2 text-info mb-2 opacity-50"></i>
                        <h6 class="text-light mb-1">Database Observation Standby</h6>
                        <p class="text-muted small mb-0">
                            Passive DB::listen query profiling is configured with zero monitoring SQL queries.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Collectors Status Table -->
    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="ll-card p-3">
                <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom border-secondary-subtle">
                    <div>
                        <h6 class="mb-0 fw-bold text-light">Monitoring Architecture & Collector Status</h6>
                        <small class="text-muted">Component readiness and non-negotiable architectural constraints</small>
                    </div>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle ll-badge-pill">
                        Phase 1 Foundation
                    </span>
                </div>

                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle mb-0" style="background: transparent;">
                        <thead>
                            <tr class="text-muted small text-uppercase" style="border-color: var(--ll-card-border);">
                                <th scope="col">Collector Module</th>
                                <th scope="col">Status</th>
                                <th scope="col">Database Overhead</th>
                                <th scope="col">Description</th>
                                <th scope="col" class="text-end">Target Phase</th>
                            </tr>
                        </thead>
                        <tbody style="border-color: var(--ll-card-border);">
                            @foreach($collectors as $key => $collector)
                            <tr>
                                <td class="fw-semibold text-light">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="fa-solid fa-circle-dot text-{{ $collector['badge'] }}" style="font-size: 0.65rem;"></i>
                                        <span>{{ $collector['name'] }}</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-{{ $collector['badge'] }}-subtle text-{{ $collector['badge'] }} border border-{{ $collector['badge'] }}-subtle ll-badge-pill">
                                        {{ $collector['label'] }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle ll-badge-pill">
                                        0 Queries
                                    </span>
                                </td>
                                <td class="text-muted small">{{ $collector['description'] }}</td>
                                <td class="text-end">
                                    <span class="badge bg-dark border border-secondary text-light ll-badge-pill">
                                        @switch($key)
                                            @case('logs') Phase 2 @break
                                            @case('database') Phase 3 @break
                                            @case('http') Phase 3 @break
                                            @case('apache') Phase 4 @break
                                            @case('system') Phase 5 @break
                                            @default Phase 5
                                        @endswitch
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Destructive Action & CSRF Protection Convention Demo -->
    <div class="row g-4">
        <div class="col-12 col-lg-6">
            <div class="ll-card p-3">
                <h6 class="fw-bold text-light mb-2">Destructive Operations & Authorization</h6>
                <p class="text-muted small mb-3">
                    Destructive operations (e.g. log cleanup, metric purging) require explicit confirmation, dedicated gate authorization (<code class="text-info">{{ config('loglens.auth.destructive_gate', 'manageLogLens') }}</code>), and CSRF validation. By default, destructive operations are disabled in configuration.
                </p>

                <form id="destructiveActionForm" method="POST" action="{{ route('loglens.actions.execute') }}">
                    @csrf
                    <input type="hidden" name="action" value="clear_logs">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="confirmed" value="1" id="confirmCheck">
                        <label class="form-check-label small text-muted" for="confirmCheck">
                            I understand that destructive operations permanently delete data and require authorization.
                        </label>
                    </div>

                    <button type="button" class="btn btn-outline-danger btn-sm" id="submitDestructiveBtn" disabled>
                        <i class="fa-solid fa-trash-can me-1"></i> Execute Protected Operation (Demo)
                    </button>
                    <span id="destructiveStatus" class="ms-2 small text-muted"></span>
                </form>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="ll-card p-3">
                <h6 class="fw-bold text-light mb-2">Configuration Summary</h6>
                <div class="small">
                    <div class="d-flex justify-content-between py-1 border-bottom border-secondary-subtle">
                        <span class="text-muted">Master Switch</span>
                        <span class="text-success fw-bold">Active ({{ config('loglens.enabled') ? 'true' : 'false' }})</span>
                    </div>
                    <div class="d-flex justify-content-between py-1 border-bottom border-secondary-subtle">
                        <span class="text-muted">Dashboard Route Prefix</span>
                        <span class="text-light fw-mono">/{{ config('loglens.path') }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-1 border-bottom border-secondary-subtle">
                        <span class="text-muted">Authorization Gate</span>
                        <span class="text-info fw-mono">{{ config('loglens.auth.gate') }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-1 border-bottom border-secondary-subtle">
                        <span class="text-muted">Destructive Actions Allowed</span>
                        <span class="text-{{ config('loglens.cleanup.allow_destructive_actions') ? 'warning' : 'muted' }}">
                            {{ config('loglens.cleanup.allow_destructive_actions') ? 'Yes' : 'No (Strict Default)' }}
                        </span>
                    </div>
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-muted">Automatic Log Deletion</span>
                        <span class="text-danger fw-bold">Disabled (Never delete silently)</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const confirmCheck = document.getElementById('confirmCheck');
        const submitBtn = document.getElementById('submitDestructiveBtn');
        const statusEl = document.getElementById('destructiveStatus');

        if (confirmCheck && submitBtn) {
            confirmCheck.addEventListener('change', function () {
                submitBtn.disabled = !this.checked;
            });

            submitBtn.addEventListener('click', function () {
                statusEl.textContent = 'Submitting...';
                const form = document.getElementById('destructiveActionForm');
                const formData = new FormData(form);

                fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: formData
                })
                .then(response => response.json().then(data => ({ status: response.status, body: data })))
                .then(result => {
                    if (result.status === 200) {
                        statusEl.className = 'ms-2 small text-success';
                        statusEl.textContent = result.body.message;
                    } else {
                        statusEl.className = 'ms-2 small text-danger';
                        statusEl.textContent = result.body.message || 'Operation rejected (403 Unauthorized).';
                    }
                })
                .catch(err => {
                    statusEl.className = 'ms-2 small text-danger';
                    statusEl.textContent = 'Error executing request.';
                });
            });
        }
    });
</script>
@endpush
