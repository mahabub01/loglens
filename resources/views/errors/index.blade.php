@extends('loglens::layouts.app', ['title' => 'Error Analysis & Grouping'])

@section('content')
<div class="container-fluid p-0">
    <!-- Top Action Bar: File Selection -->
    <div class="ll-card p-3 mb-4">
        <div class="row g-3 align-items-center justify-content-between">
            <div class="col-12 col-md-5">
                <form method="GET" action="{{ route('loglens.errors.index') }}" id="errorFileSelectForm">
                    <div class="input-group">
                        <span class="input-group-text bg-dark border-secondary text-muted">
                            <i class="fa-solid fa-triangle-exclamation text-warning"></i>
                        </span>
                        <select name="file" class="form-select bg-dark text-light border-secondary" onchange="document.getElementById('errorFileSelectForm').submit();">
                            @forelse($files as $id => $f)
                                <option value="{{ $id }}" {{ ($selectedFile && $selectedFile->identifier === $id) ? 'selected' : '' }}>
                                    {{ $f->filename }} ({{ $f->formattedSize() }})
                                </option>
                            @empty
                                <option disabled selected>No log files found</option>
                            @endforelse
                        </select>
                    </div>
                </form>
            </div>

            <div class="col-12 col-md-7 text-md-end text-muted small">
                <span>Analyzing recent entries &bull; Normalized fingerprint grouping &bull; Zero DB queries</span>
            </div>
        </div>
    </div>

    <!-- Summary Metrics -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="ll-card p-3 h-100">
                <span class="ll-stat-title">Analyzed Entries</span>
                <div class="ll-stat-value text-light">{{ number_format($analysis['total_entries']) }}</div>
                <div class="small text-muted border-top border-secondary-subtle pt-2 mt-2">
                    Recent scan window
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="ll-card p-3 h-100">
                <span class="ll-stat-title text-danger">Total Errors</span>
                <div class="ll-stat-value text-danger">{{ number_format($analysis['error_count']) }}</div>
                <div class="small text-muted border-top border-secondary-subtle pt-2 mt-2">
                    Critical, alert & errors
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="ll-card p-3 h-100">
                <span class="ll-stat-title text-warning">Warnings</span>
                <div class="ll-stat-value text-warning">{{ number_format($analysis['warning_count']) }}</div>
                <div class="small text-muted border-top border-secondary-subtle pt-2 mt-2">
                    Non-critical alerts
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="ll-card p-3 h-100">
                <span class="ll-stat-title text-info">Unique Fingerprints</span>
                <div class="ll-stat-value text-info">{{ number_format($analysis['unique_error_groups']) }}</div>
                <div class="small text-muted border-top border-secondary-subtle pt-2 mt-2">
                    Distinct root causes
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Section (Chart.js) -->
    <div class="row g-4 mb-4">
        <!-- Error Timeline Chart -->
        <div class="col-12 col-xl-8">
            <div class="ll-card p-3 h-100">
                <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom border-secondary-subtle">
                    <div>
                        <h6 class="mb-0 fw-bold text-light">Error Frequency Timeline</h6>
                        <small class="text-muted">Observed error occurrences over time</small>
                    </div>
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle ll-badge-pill">
                        Live Analysis
                    </span>
                </div>
                <div class="ll-chart-box" style="height: 240px;">
                    <canvas id="errorTimelineChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Severity Distribution Chart -->
        <div class="col-12 col-xl-4">
            <div class="ll-card p-3 h-100">
                <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom border-secondary-subtle">
                    <div>
                        <h6 class="mb-0 fw-bold text-light">Severity Breakdown</h6>
                        <small class="text-muted">Proportion of log levels</small>
                    </div>
                </div>
                <div class="ll-chart-box d-flex align-items-center justify-content-center" style="height: 240px;">
                    <canvas id="severityDoughnutChart" style="max-height: 220px;"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Error Groups Table -->
    <div class="ll-card p-3 mb-4">
        <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom border-secondary-subtle">
            <div>
                <h6 class="mb-0 fw-bold text-light">Grouped Errors by Normalized Fingerprint</h6>
                <small class="text-muted">Errors clustered by exception type and normalized message signature</small>
            </div>
            <span class="badge bg-secondary text-uppercase ll-badge-pill">
                {{ count($analysis['groups']) }} Groups
            </span>
        </div>

        @if(empty($analysis['groups']))
            <div class="p-5 text-center text-muted">
                <i class="fa-regular fa-circle-check fs-1 text-success mb-3"></i>
                <h5>No errors detected</h5>
                <p class="small mb-0">No errors or exceptions exist in the scanned window of this log file.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-dark table-hover align-middle mb-0" style="background: transparent;">
                    <thead>
                        <tr class="text-muted small text-uppercase" style="border-color: var(--ll-card-border);">
                            <th scope="col" style="width: 90px;">Count</th>
                            <th scope="col">Exception & Normalized Signature</th>
                            <th scope="col" style="width: 130px;">First / Last Seen</th>
                            <th scope="col" style="width: 100px;">Fingerprint</th>
                            <th scope="col" class="text-end" style="width: 100px;">Action</th>
                        </tr>
                    </thead>
                    <tbody style="border-color: var(--ll-card-border);">
                        @foreach($analysis['groups'] as $index => $group)
                            <tr>
                                <td>
                                    <span class="badge bg-danger rounded-pill fs-6 px-3 py-1">
                                        {{ $group->count }} &times;
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle ll-badge-pill font-monospace">
                                            {{ $group->exceptionClass }}
                                        </span>
                                        @if($group->isNew(120))
                                            <span class="badge bg-warning text-dark ll-badge-pill">NEW</span>
                                        @endif
                                    </div>
                                    <div class="font-monospace small text-light text-break">
                                        {{ $group->normalizedMessage }}
                                    </div>
                                </td>
                                <td class="small text-muted font-monospace" style="font-size: 0.75rem;">
                                    <div><i class="fa-solid fa-arrow-up text-secondary me-1"></i> {{ $group->lastSeen->format('M d H:i') }}</div>
                                    <div><i class="fa-solid fa-arrow-down text-secondary me-1"></i> {{ $group->firstSeen->format('M d H:i') }}</div>
                                </td>
                                <td>
                                    <span class="badge bg-dark border border-secondary text-muted font-monospace" style="font-size: 0.72rem;">
                                        {{ $group->fingerprint }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-outline-info btn-sm py-1 px-2" type="button" data-bs-toggle="collapse" data-bs-target="#trace-group-{{ $index }}" aria-expanded="false" title="View sample trace">
                                        <i class="fa-solid fa-code"></i>
                                    </button>
                                </td>
                            </tr>
                            <tr class="collapse" id="trace-group-{{ $index }}">
                                <td colspan="5" class="bg-black p-3 border-bottom border-secondary">
                                    <div class="small fw-semibold text-warning mb-1">Sample Error Message:</div>
                                    <pre class="bg-dark p-2 rounded text-light small font-monospace text-break mb-2"><code>{{ $group->sampleEntry->message }}</code></pre>
                                    @if($group->sampleEntry->stackTrace)
                                        <div class="small fw-semibold text-warning mb-1">Stack Trace:</div>
                                        <pre class="bg-dark p-2 rounded text-danger-emphasis small font-monospace overflow-x-auto" style="max-height: 250px;"><code>{{ $group->sampleEntry->stackTrace }}</code></pre>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const timelineData = {!! json_encode($analysis['timeline']) !!};
    const severityData = {!! json_encode($analysis['severity_breakdown']) !!};

    // 1. Error Timeline Chart
    const timelineCtx = document.getElementById('errorTimelineChart');
    if (timelineCtx && timelineData.labels.length > 0) {
        new Chart(timelineCtx, {
            type: 'line',
            data: {
                labels: timelineData.labels,
                datasets: [{
                    label: 'Errors Observed',
                    data: timelineData.counts,
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.15)',
                    tension: 0.3,
                    fill: true,
                    pointBackgroundColor: '#ef4444',
                    pointRadius: 4,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        ticks: { color: '#94a3b8', font: { size: 11 } },
                        grid: { color: 'rgba(255, 255, 255, 0.05)' }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { color: '#94a3b8', stepSize: 1 },
                        grid: { color: 'rgba(255, 255, 255, 0.05)' }
                    }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }

    // 2. Severity Breakdown Chart
    const severityCtx = document.getElementById('severityDoughnutChart');
    if (severityCtx) {
        const labels = Object.keys(severityData);
        const data = Object.values(severityData);

        const colors = {
            'EMERGENCY': '#7f1d1d',
            'ALERT': '#991b1b',
            'CRITICAL': '#b91c1c',
            'ERROR': '#ef4444',
            'WARNING': '#f59e0b',
            'NOTICE': '#06b6d4',
            'INFO': '#3b82f6',
            'DEBUG': '#64748b'
        };

        const backgroundColors = labels.map(l => colors[l] || '#6b7280');

        new Chart(severityCtx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: backgroundColors,
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
