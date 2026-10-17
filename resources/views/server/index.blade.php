@extends('loglens::layouts.app', ['title' => 'Server Health & Resources'])

@section('content')
<div class="container-fluid p-0">
    <!-- Notice Banner -->
    <div class="alert alert-info border-info-subtle bg-info-subtle text-info-emphasis d-flex align-items-center justify-content-between p-3 mb-4 rounded-3 shadow-sm" role="alert">
        <div class="d-flex align-items-center gap-3">
            <span class="fs-4"><i class="fa-solid fa-heart-pulse text-info"></i></span>
            <div>
                <strong class="d-block mb-1">
                    Operating System &amp; Host Resource Monitor
                    <span class="badge bg-dark border border-secondary-subtle text-light ms-2 fw-normal">
                        {{ $platform['os'] }} (PHP {{ $platform['php_version'] }})
                    </span>
                    @if($platform['is_docker'])
                        <span class="badge bg-primary text-white ms-1 fw-normal"><i class="fa-brands fa-docker me-1"></i> Container (cgroups {{ $platform['cgroup_version'] ? 'v' . $platform['cgroup_version'] : 'N/A' }})</span>
                    @endif
                </strong>
                <span class="small">
                    Probing native OS interfaces (<code>/proc</code>, cgroups, disk filesystem) safely without executing arbitrary shell commands.
                </span>
            </div>
        </div>
        <span class="badge bg-{{ $is_enabled ? 'success' : 'secondary' }} text-white d-none d-md-inline-block">
            {{ $is_enabled ? 'Probes Active' : 'Probes Standby' }}
        </span>
    </div>

    <!-- Active Alerts Banner if any -->
    @if(!empty($alerts))
    <div class="mb-4">
        @foreach($alerts as $alert)
        <div class="alert alert-{{ $alert['severity'] === 'critical' ? 'danger' : 'warning' }} bg-{{ $alert['severity'] === 'critical' ? 'danger' : 'warning' }}-subtle text-{{ $alert['severity'] === 'critical' ? 'danger' : 'warning' }}-emphasis border-{{ $alert['severity'] === 'critical' ? 'danger' : 'warning' }}-subtle p-3 mb-2 rounded-3 d-flex align-items-center justify-content-between shadow-sm" role="alert">
            <div class="d-flex align-items-center gap-3">
                <i class="fa-solid fa-triangle-exclamation fs-4 text-{{ $alert['severity'] === 'critical' ? 'danger' : 'warning' }}"></i>
                <div>
                    <strong>{{ $alert['title'] }}</strong>
                    <span class="d-block small">{{ $alert['message'] }}</span>
                </div>
            </div>
            <span class="badge bg-{{ $alert['severity'] === 'critical' ? 'danger' : 'warning' }} text-white text-uppercase">
                {{ strtoupper($alert['severity']) }}
            </span>
        </div>
        @endforeach
    </div>
    @endif

    <!-- Quick Metric Cards -->
    <div class="row g-3 mb-4">
        <!-- CPU / Load Average -->
        <div class="col-12 col-sm-6 col-xl-4 col-xxl-2">
            <div class="ll-card p-3 h-100">
                <span class="ll-stat-title text-info">CPU Utilization</span>
                <div class="ll-stat-value text-info">
                    @if(isset($metrics['cpu']['utilization_percent']) && $metrics['cpu']['utilization_percent'] !== null)
                        {{ $metrics['cpu']['utilization_percent'] }}%
                    @else
                        <span class="text-muted fs-5">N/A</span>
                    @endif
                </div>
                <div class="d-flex align-items-center justify-content-between mt-2 pt-2 border-top border-secondary-subtle">
                    <span class="small text-muted">Load (1/5/15m)</span>
                    <span class="small text-light">
                        @if($metrics['load_average'])
                            {{ $metrics['load_average']['1m'] }}, {{ $metrics['load_average']['5m'] }}
                        @else
                            N/A
                        @endif
                    </span>
                </div>
            </div>
        </div>

        <!-- RAM Utilization -->
        @php
            $mem = $metrics['memory'];
            $memTotalGb = isset($mem['total_bytes']) ? round($mem['total_bytes'] / 1073741824, 2) : null;
            $memUsedGb = isset($mem['used_bytes']) ? round($mem['used_bytes'] / 1073741824, 2) : null;
        @endphp
        <div class="col-12 col-sm-6 col-xl-4 col-xxl-2">
            <div class="ll-card p-3 h-100">
                <span class="ll-stat-title text-primary">System Memory</span>
                <div class="ll-stat-value text-primary">
                    @if(isset($mem['used_percent']) && $mem['used_percent'] !== null)
                        {{ $mem['used_percent'] }}%
                    @else
                        <span class="text-muted fs-5">N/A</span>
                    @endif
                </div>
                <div class="d-flex align-items-center justify-content-between mt-2 pt-2 border-top border-secondary-subtle">
                    <span class="small text-muted">Usage</span>
                    <span class="small text-light">
                        @if($memUsedGb !== null)
                            {{ $memUsedGb }} / {{ $memTotalGb }} GB
                        @else
                            Process: {{ round($mem['process_usage_bytes'] / 1048576, 1) }} MB
                        @endif
                    </span>
                </div>
            </div>
        </div>

        <!-- Swap Memory -->
        @php
            $swap = $metrics['swap'];
            $swapTotalGb = isset($swap['total_bytes']) ? round($swap['total_bytes'] / 1073741824, 2) : null;
        @endphp
        <div class="col-12 col-sm-6 col-xl-4 col-xxl-2">
            <div class="ll-card p-3 h-100">
                <span class="ll-stat-title">Swap Space</span>
                <div class="ll-stat-value text-light">
                    @if(isset($swap['used_percent']) && $swap['used_percent'] !== null)
                        {{ $swap['used_percent'] }}%
                    @else
                        <span class="text-muted fs-5">N/A</span>
                    @endif
                </div>
                <div class="d-flex align-items-center justify-content-between mt-2 pt-2 border-top border-secondary-subtle">
                    <span class="small text-muted">Total</span>
                    <span class="small text-muted">{{ $swapTotalGb !== null ? $swapTotalGb . ' GB' : 'None / N/A' }}</span>
                </div>
            </div>
        </div>

        <!-- Root Disk Usage -->
        @php
            $disk = $metrics['disk'];
            $diskTotalGb = isset($disk['total_bytes']) ? round($disk['total_bytes'] / 1073741824, 1) : null;
            $diskFreeGb = isset($disk['free_bytes']) ? round($disk['free_bytes'] / 1073741824, 1) : null;
        @endphp
        <div class="col-12 col-sm-6 col-xl-4 col-xxl-2">
            <div class="ll-card p-3 h-100">
                <span class="ll-stat-title text-warning">Disk Space</span>
                <div class="ll-stat-value text-{{ ($disk['used_percent'] ?? 0) >= 90 ? 'danger' : 'warning' }}">
                    @if(isset($disk['used_percent']))
                        {{ $disk['used_percent'] }}%
                    @else
                        <span class="text-muted fs-5">N/A</span>
                    @endif
                </div>
                <div class="d-flex align-items-center justify-content-between mt-2 pt-2 border-top border-secondary-subtle">
                    <span class="small text-muted">Free Space</span>
                    <span class="small text-light">{{ $diskFreeGb !== null ? $diskFreeGb . ' GB' : 'N/A' }}</span>
                </div>
            </div>
        </div>

        <!-- Application Logs Size -->
        @php
            $logs = $metrics['log_directory'];
            $logsSizeMb = round($logs['total_bytes'] / 1048576, 2);
        @endphp
        <div class="col-12 col-sm-6 col-xl-4 col-xxl-2">
            <div class="ll-card p-3 h-100">
                <span class="ll-stat-title">App Logs Size</span>
                <div class="ll-stat-value text-light">
                    {{ $logsSizeMb }} <span class="fs-6 text-muted">MB</span>
                </div>
                <div class="d-flex align-items-center justify-content-between mt-2 pt-2 border-top border-secondary-subtle">
                    <span class="small text-muted">Log Files</span>
                    <span class="badge bg-secondary-subtle text-light ll-badge-pill">{{ $logs['file_count'] }} files</span>
                </div>
            </div>
        </div>

        <!-- Network I/O -->
        @php
            $net = $metrics['network'];
            $rxMb = isset($net['rx_bytes']) ? round($net['rx_bytes'] / 1048576, 1) : null;
            $txMb = isset($net['tx_bytes']) ? round($net['tx_bytes'] / 1048576, 1) : null;
        @endphp
        <div class="col-12 col-sm-6 col-xl-4 col-xxl-2">
            <div class="ll-card p-3 h-100">
                <span class="ll-stat-title">Network I/O</span>
                <div class="ll-stat-value text-light">
                    @if($rxMb !== null)
                        {{ $rxMb }} <span class="fs-6 text-muted">MB In</span>
                    @else
                        <span class="text-muted fs-5">N/A</span>
                    @endif
                </div>
                <div class="d-flex align-items-center justify-content-between mt-2 pt-2 border-top border-secondary-subtle">
                    <span class="small text-muted">Outbound</span>
                    <span class="small text-muted">{{ $txMb !== null ? $txMb . ' MB Out' : 'Unavailable' }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Resource Breakdown -->
    <div class="row g-4 mb-4">
        <!-- Storage & Filesystem Breakdown -->
        <div class="col-12 col-xl-6">
            <div class="ll-card p-3 h-100">
                <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom border-secondary-subtle">
                    <div>
                        <h6 class="mb-0 fw-bold text-light">Storage &amp; Filesystem Allocation</h6>
                        <small class="text-muted">Probed mount points and log directory utilization</small>
                    </div>
                    <span class="badge bg-secondary-subtle text-light ll-badge-pill">Filesystem</span>
                </div>

                @if($disk)
                <div class="mb-4">
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="text-light">Mount Path: <code>{{ $disk['path'] }}</code></span>
                        <span class="text-light fw-bold">{{ $disk['used_percent'] }}% Used</span>
                    </div>
                    <div class="progress" style="height: 10px; background-color: rgba(255,255,255,0.08);">
                        <div class="progress-bar bg-{{ $disk['used_percent'] >= 90 ? 'danger' : ($disk['used_percent'] >= 75 ? 'warning' : 'primary') }}"
                             role="progressbar"
                             style="width: {{ $disk['used_percent'] }}%;"
                             aria-valuenow="{{ $disk['used_percent'] }}"
                             aria-valuemin="0"
                             aria-valuemax="100"></div>
                    </div>
                    <div class="d-flex justify-content-between text-muted small mt-1">
                        <span>Used: {{ round($disk['used_bytes'] / 1073741824, 2) }} GB</span>
                        <span>Free: {{ round($disk['free_bytes'] / 1073741824, 2) }} GB (Total: {{ $diskTotalGb }} GB)</span>
                    </div>
                </div>
                @else
                <div class="p-3 text-muted small text-center">Disk metrics unavailable on this mount.</div>
                @endif

                <!-- Application Logs Directory Bar -->
                <div>
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="text-light">Application Logs Storage</span>
                        <span class="text-light fw-bold">{{ $logsSizeMb }} MB</span>
                    </div>
                    <div class="p-2 rounded bg-dark border border-secondary-subtle small text-muted">
                        Total of <strong>{{ $logs['file_count'] }}</strong> active log files across configured paths.
                    </div>
                </div>
            </div>
        </div>

        <!-- Platform & Container Environment Details -->
        <div class="col-12 col-xl-6">
            <div class="ll-card p-3 h-100">
                <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom border-secondary-subtle">
                    <div>
                        <h6 class="mb-0 fw-bold text-light">Runtime &amp; Hosting Environment</h6>
                        <small class="text-muted">Detected operating system and sandbox isolation parameters</small>
                    </div>
                    <span class="badge bg-primary-subtle text-primary ll-badge-pill">Environment</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-dark table-sm table-borderless align-middle mb-0" style="font-size: 0.85rem;">
                        <tbody>
                            <tr>
                                <td class="text-muted" style="width: 35%;">Operating System</td>
                                <td class="text-light fw-semibold">{{ $platform['os'] }} ({{ php_uname('s') }} {{ php_uname('r') }})</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Hostname</td>
                                <td class="text-light">{{ $platform['hostname'] }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">PHP Version</td>
                                <td class="text-light">{{ $platform['php_version'] }} (SAPI: {{ PHP_SAPI }})</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Container / Docker</td>
                                <td>
                                    @if($platform['is_docker'])
                                        <span class="badge bg-primary text-white"><i class="fa-brands fa-docker me-1"></i> Detected (cgroups {{ $platform['cgroup_version'] ? 'v' . $platform['cgroup_version'] : 'v1' }})</span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-muted">Direct Host / VM</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted">Memory Source</td>
                                <td class="text-light">
                                    <code>{{ $mem['source'] }}</code>
                                    @if(! $mem['is_available'])
                                        <span class="text-warning small ms-1">(Host memory probes require Linux /proc)</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted">Process Memory</td>
                                <td class="text-light">{{ round($mem['process_usage_bytes'] / 1048576, 2) }} MB (Peak: {{ round($mem['process_peak_bytes'] / 1048576, 2) }} MB)</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
