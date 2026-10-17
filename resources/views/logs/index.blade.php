@extends('loglens::layouts.app', ['title' => 'Log File Analyzer'])

@section('content')
<div class="container-fluid p-0">
    <!-- Top Action Bar: File Selection & Actions -->
    <div class="ll-card p-3 mb-4">
        <div class="row g-3 align-items-center">
            <!-- File Selector -->
            <div class="col-12 col-md-5 col-lg-4">
                <form method="GET" action="{{ route('loglens.logs.index') }}" id="fileSelectForm">
                    <div class="input-group">
                        <span class="input-group-text bg-dark border-secondary text-muted">
                            <i class="fa-solid fa-file-code"></i>
                        </span>
                        <select name="file" class="form-select bg-dark text-light border-secondary" onchange="document.getElementById('fileSelectForm').submit();">
                            @forelse($files as $id => $f)
                                <option value="{{ $id }}" {{ ($selectedFile && $selectedFile->identifier === $id) ? 'selected' : '' }}>
                                    {{ $f->filename }} ({{ $f->formattedSize() }})
                                </option>
                            @empty
                                <option disabled selected>No log files found in configured paths</option>
                            @endforelse
                        </select>
                    </div>
                </form>
            </div>

            <!-- Metadata info -->
            <div class="col-12 col-md-3 col-lg-4 text-muted small">
                @if($selectedFile)
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <span class="badge bg-secondary-subtle text-light border border-secondary-subtle ll-badge-pill">
                            <i class="fa-regular fa-clock me-1"></i> {{ $selectedFile->modifiedAt->format('M d, Y H:i:s') }}
                        </span>
                        <span class="badge bg-secondary-subtle text-light border border-secondary-subtle ll-badge-pill">
                            <i class="fa-solid fa-hard-drive me-1"></i> {{ $selectedFile->formattedSize() }}
                        </span>
                        @if($selectedFile->isReadOnly)
                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle ll-badge-pill">
                                Read Only
                            </span>
                        @endif
                    </div>
                @endif
            </div>

            <!-- Action Buttons -->
            <div class="col-12 col-md-4 col-lg-4 text-md-end">
                <div class="btn-group">
                    @if($selectedFile)
                        <a href="{{ route('loglens.logs.download', $selectedFile->identifier) }}" class="btn btn-outline-secondary btn-sm" title="Download Log File">
                            <i class="fa-solid fa-download me-1"></i> Download
                        </a>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="toggleLiveTailBtn">
                            <i class="fa-solid fa-satellite-dish me-1"></i> <span id="tailBtnText">Live Tail</span>
                        </button>
                        @if(!$selectedFile->isReadOnly)
                            <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#clearFileModal">
                                <i class="fa-solid fa-broom me-1"></i> Clear Log
                            </button>
                        @endif
                    @endif
                    <button type="button" class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#retentionModal">
                        <i class="fa-solid fa-clock-rotate-left me-1"></i> Retention
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Live Tail Panel (Hidden by default, toggles via JS) -->
    <div id="liveTailPanel" class="ll-card p-3 mb-4 d-none border-primary">
        <div class="d-flex align-items-center justify-content-between mb-2 pb-2 border-bottom border-secondary-subtle">
            <div class="d-flex align-items-center gap-2">
                <span class="spinner-grow spinner-grow-sm text-danger" role="status" id="tailSpinner"></span>
                <span class="fw-bold text-light">Live Log Stream</span>
                <span class="badge bg-dark border border-secondary text-muted ll-badge-pill" id="tailStatus">Connecting...</span>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="pauseResumeTailBtn">
                    <i class="fa-solid fa-pause me-1"></i> Pause
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="clearTailDisplayBtn">
                    <i class="fa-solid fa-trash me-1"></i> Clear Feed
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger" id="closeTailBtn">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        </div>
        <div id="liveTailFeed" class="p-2 rounded bg-black overflow-y-auto font-monospace text-light small" style="max-height: 320px; min-height: 120px; font-size: 0.82rem;">
            <div class="text-muted text-center py-3">Listening for newly appended log entries...</div>
        </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="ll-card p-3 mb-4">
        <form method="GET" action="{{ route('loglens.logs.index') }}" class="row g-2 align-items-center">
            @if($selectedFile)
                <input type="hidden" name="file" value="{{ $selectedFile->identifier }}">
            @endif

            <!-- Severity Filter -->
            <div class="col-12 col-sm-6 col-md-2">
                <select name="level" class="form-select form-select-sm bg-dark text-light border-secondary">
                    <option value="ALL" {{ ($filters['level'] ?? '') === 'ALL' ? 'selected' : '' }}>All Severities</option>
                    @foreach(['EMERGENCY', 'ALERT', 'CRITICAL', 'ERROR', 'WARNING', 'NOTICE', 'INFO', 'DEBUG'] as $lvl)
                        <option value="{{ $lvl }}" {{ ($filters['level'] ?? '') === $lvl ? 'selected' : '' }}>{{ $lvl }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Keyword Query -->
            <div class="col-12 col-sm-6 col-md-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-dark text-muted border-secondary"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control bg-dark text-light border-secondary" placeholder="Search message, exception, or file...">
                </div>
            </div>

            <!-- Date From -->
            <div class="col-6 col-md-2">
                <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control form-control-sm bg-dark text-light border-secondary" title="Date From">
            </div>

            <!-- Date To -->
            <div class="col-6 col-md-2">
                <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control form-control-sm bg-dark text-light border-secondary" title="Date To">
            </div>

            <!-- Filter Buttons -->
            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                    <i class="fa-solid fa-filter me-1"></i> Filter
                </button>
                @if(!empty($filters['q']) || ($filters['level'] ?? 'ALL') !== 'ALL' || !empty($filters['from']) || !empty($filters['to']))
                    <a href="{{ route('loglens.logs.index', ['file' => $selectedFile?->identifier]) }}" class="btn btn-outline-secondary btn-sm" title="Reset Filters">
                        <i class="fa-solid fa-rotate-left"></i>
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Log Entries Feed -->
    @if(empty($entries))
        <div class="ll-card p-5 text-center text-muted">
            <i class="fa-solid fa-magnifying-glass-chart fs-1 mb-3 text-secondary opacity-50"></i>
            <h5>No log entries found</h5>
            <p class="small mb-0">
                @if(!$selectedFile)
                    No log files exist in the configured log paths.
                @else
                    No log records match your current filter criteria in <code>{{ $selectedFile->filename }}</code>.
                @endif
            </p>
        </div>
    @else
        <div class="d-flex flex-column gap-3 mb-4">
            @foreach($entries as $index => $entry)
                <div class="ll-card p-3">
                    <!-- Entry Header -->
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2 pb-2 border-bottom border-secondary-subtle">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-{{ $entry->badgeClass() }} text-white ll-badge-pill text-uppercase">
                                {{ $entry->severity }}
                            </span>
                            <span class="badge bg-dark border border-secondary text-muted ll-badge-pill font-monospace">
                                {{ $entry->channel }}
                            </span>
                            <span class="small text-muted">
                                <i class="fa-regular fa-clock me-1"></i> {{ $entry->timestamp->format('Y-m-d H:i:s') }}
                            </span>
                        </div>

                        <div class="d-flex align-items-center gap-2">
                            @if($entry->exceptionClass)
                                <span class="badge bg-info-subtle text-info border border-info-subtle ll-badge-pill font-monospace" style="font-size: 0.75rem;">
                                    {{ class_basename($entry->exceptionClass) }}
                                </span>
                            @endif
                            <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 copy-btn" data-content="{{ $entry->message }}" title="Copy message">
                                <i class="fa-regular fa-copy"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Message Body -->
                    <div class="text-light font-monospace small mb-2 text-break" style="white-space: pre-wrap; line-height: 1.5;">{{ $entry->message }}</div>

                    <!-- Context JSON (if present) -->
                    @if(!empty($entry->context))
                        <div class="mb-2">
                            <a class="text-decoration-none small text-info" data-bs-toggle="collapse" href="#context-{{ $entry->id }}" role="button" aria-expanded="false">
                                <i class="fa-solid fa-cube me-1"></i> View Context ({{ count($entry->context) }} items)
                            </a>
                            <div class="collapse mt-2" id="context-{{ $entry->id }}">
                                <pre class="bg-black p-2 rounded text-info small mb-0 font-monospace overflow-x-auto"><code>{{ json_encode($entry->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</code></pre>
                            </div>
                        </div>
                    @endif

                    <!-- Stack Trace Accordion (if present) -->
                    @if(!empty($entry->stackTrace))
                        <div>
                            <a class="text-decoration-none small text-warning" data-bs-toggle="collapse" href="#trace-{{ $entry->id }}" role="button" aria-expanded="false">
                                <i class="fa-solid fa-code-branch me-1"></i> View Stack Trace
                            </a>
                            <div class="collapse mt-2" id="trace-{{ $entry->id }}">
                                <pre class="bg-black p-3 rounded text-danger-emphasis small mb-0 font-monospace overflow-x-auto" style="font-size: 0.78rem; max-height: 350px;"><code>{{ $entry->stackTrace }}</code></pre>
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <!-- Pagination Bar -->
        <div class="d-flex align-items-center justify-content-between p-3 ll-card">
            <span class="small text-muted">
                Page {{ $pagination['page'] }} &bull; Scanned {{ $pagination['total_scanned'] }} entries
            </span>
            <div class="btn-group btn-group-sm">
                @if($pagination['page'] > 1)
                    <a href="{{ route('loglens.logs.index', array_merge($filters, ['file' => $selectedFile?->identifier, 'page' => $pagination['page'] - 1])) }}" class="btn btn-outline-secondary">
                        <i class="fa-solid fa-arrow-left me-1"></i> Newer
                    </a>
                @endif
                @if($pagination['has_more'])
                    <a href="{{ route('loglens.logs.index', array_merge($filters, ['file' => $selectedFile?->identifier, 'page' => $pagination['page'] + 1])) }}" class="btn btn-outline-secondary">
                        Older <i class="fa-solid fa-arrow-right ms-1"></i>
                    </a>
                @endif
            </div>
        </div>
    @endif
</div>

<!-- Modal: Clear Single File -->
@if($selectedFile && !$selectedFile->isReadOnly)
<div class="modal fade" id="clearFileModal" tabindex="-1" aria-labelledby="clearFileModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-light border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="clearFileModalLabel">
                    <i class="fa-solid fa-triangle-exclamation text-warning me-2"></i> Clear Log File
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to clear <strong>{{ $selectedFile->filename }}</strong> ({{ $selectedFile->formattedSize() }})?</p>
                <div class="alert alert-warning small bg-warning-subtle text-warning-emphasis border-warning-subtle mb-3">
                    This file will be safely truncated in place without affecting active application workers.
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="archiveBeforeClear" checked>
                    <label class="form-check-label small" for="archiveBeforeClear">
                        Create an archive backup before clearing
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="confirmClearCheckbox">
                    <label class="form-check-label small text-danger" for="confirmClearCheckbox">
                        I confirm that I want to clear this file permanently.
                    </label>
                </div>
                <div id="clearFileErrorMsg" class="text-danger small mt-2"></div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger btn-sm" id="submitClearFileBtn" disabled>
                    <i class="fa-solid fa-broom me-1"></i> Clear File
                </button>
            </div>
        </div>
    </div>
</div>
@endif

<!-- Modal: Retention Cleanup -->
<div class="modal fade" id="retentionModal" tabindex="-1" aria-labelledby="retentionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark text-light border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="retentionModalLabel">
                    <i class="fa-solid fa-clock-rotate-left text-info me-2"></i> Log Retention Cleanup
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted">
                    Automatically scans and removes aged daily log files older than your configured threshold. Primary log files (such as <code>laravel.log</code>) and read-only log sources are preserved.
                </p>

                <div class="row g-2 align-items-center mb-3">
                    <div class="col-auto">
                        <label class="small text-light fw-semibold" for="retentionDaysInput">Retention Threshold:</label>
                    </div>
                    <div class="col-auto">
                        <div class="input-group input-group-sm">
                            <input type="number" id="retentionDaysInput" class="form-control bg-dark text-light border-secondary" value="30" min="1" max="365" style="width: 80px;">
                            <span class="input-group-text bg-dark text-muted border-secondary">days</span>
                        </div>
                    </div>
                    <div class="col-auto">
                        <button type="button" class="btn btn-outline-info btn-sm" id="previewRetentionBtn">
                            <i class="fa-solid fa-eye me-1"></i> Preview Dry-Run
                        </button>
                    </div>
                </div>

                <div id="retentionPreviewResult" class="p-3 bg-black rounded border border-secondary-subtle small font-monospace d-none mb-3">
                    <!-- Populated via AJAX -->
                </div>

                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="archiveRetentionCheckbox" checked>
                    <label class="form-check-label small" for="archiveRetentionCheckbox">
                        Archive older logs before deleting
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="confirmRetentionCheckbox">
                    <label class="form-check-label small text-danger" for="confirmRetentionCheckbox">
                        I confirm that I want to execute retention deletion for the identified files.
                    </label>
                </div>
                <div id="retentionErrorMsg" class="text-danger small mt-2"></div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-danger btn-sm" id="executeRetentionBtn" disabled>
                    <i class="fa-solid fa-trash-can me-1"></i> Execute Cleanup
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const selectedFileId = '{{ $selectedFile?->identifier }}';

    // 1. Copy to clipboard
    document.querySelectorAll('.copy-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const content = this.getAttribute('data-content');
            navigator.clipboard.writeText(content).then(() => {
                const originalHtml = this.innerHTML;
                this.innerHTML = '<i class="fa-solid fa-check text-success"></i>';
                setTimeout(() => this.innerHTML = originalHtml, 1500);
            });
        });
    });

    // 2. Clear File Modal Interaction
    const confirmClear = document.getElementById('confirmClearCheckbox');
    const submitClearBtn = document.getElementById('submitClearFileBtn');
    const clearError = document.getElementById('clearFileErrorMsg');

    if (confirmClear && submitClearBtn) {
        confirmClear.addEventListener('change', function () {
            submitClearBtn.disabled = !this.checked;
        });

        submitClearBtn.addEventListener('click', function () {
            submitClearBtn.disabled = true;
            submitClearBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Clearing...';
            clearError.textContent = '';

            const archive = document.getElementById('archiveBeforeClear').checked;

            fetch('{{ route("loglens.api.logs.clear") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    file: selectedFileId,
                    archive: archive,
                    confirmed: true
                })
            })
            .then(res => res.json().then(data => ({ status: res.status, body: data })))
            .then(result => {
                if (result.status === 200) {
                    location.reload();
                } else {
                    clearError.textContent = result.body.message || 'Operation failed (403 Unauthorized).';
                    submitClearBtn.disabled = false;
                    submitClearBtn.innerHTML = '<i class="fa-solid fa-broom me-1"></i> Clear File';
                }
            })
            .catch(() => {
                clearError.textContent = 'Server communication error.';
                submitClearBtn.disabled = false;
                submitClearBtn.innerHTML = '<i class="fa-solid fa-broom me-1"></i> Clear File';
            });
        });
    }

    // 3. Retention Cleanup Interaction
    const previewBtn = document.getElementById('previewRetentionBtn');
    const executeBtn = document.getElementById('executeRetentionBtn');
    const confirmRetention = document.getElementById('confirmRetentionCheckbox');
    const previewContainer = document.getElementById('retentionPreviewResult');
    const retentionError = document.getElementById('retentionErrorMsg');

    if (previewBtn) {
        previewBtn.addEventListener('click', function () {
            const days = document.getElementById('retentionDaysInput').value;
            previewBtn.disabled = true;
            previewBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Scanning...';

            fetch('{{ route("loglens.api.cleanup.preview") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ retention_days: days })
            })
            .then(r => r.json())
            .then(data => {
                previewBtn.disabled = false;
                previewBtn.innerHTML = '<i class="fa-solid fa-eye me-1"></i> Preview Dry-Run';
                if (data.success) {
                    previewContainer.classList.remove('d-none');
                    let html = `<strong>Candidate Files (${data.preview.files_count}):</strong> Total space to reclaim: <span class="text-success">${data.preview.formatted_size}</span><br>`;
                    if (data.preview.files.length === 0) {
                        html += '<span class="text-muted">No files older than ' + days + ' days found.</span>';
                    } else {
                        html += '<ul class="mb-0 mt-2 ps-3">';
                        data.preview.files.forEach(f => {
                            html += `<li>${f.filename} (${f.formatted_size}) - Last modified: ${f.modified_at}</li>`;
                        });
                        html += '</ul>';
                    }
                    previewContainer.innerHTML = html;
                }
            })
            .catch(() => {
                previewBtn.disabled = false;
                previewBtn.innerHTML = '<i class="fa-solid fa-eye me-1"></i> Preview Dry-Run';
            });
        });
    }

    if (confirmRetention && executeBtn) {
        confirmRetention.addEventListener('change', function () {
            executeBtn.disabled = !this.checked;
        });

        executeBtn.addEventListener('click', function () {
            executeBtn.disabled = true;
            executeBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Executing...';
            retentionError.textContent = '';

            const days = document.getElementById('retentionDaysInput').value;
            const archive = document.getElementById('archiveRetentionCheckbox').checked;

            fetch('{{ route("loglens.api.cleanup.execute") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    retention_days: days,
                    archive: archive,
                    confirmed: true
                })
            })
            .then(res => res.json().then(data => ({ status: res.status, body: data })))
            .then(result => {
                if (result.status === 200) {
                    location.reload();
                } else {
                    retentionError.textContent = result.body.message || 'Operation failed (403 Unauthorized).';
                    executeBtn.disabled = false;
                    executeBtn.innerHTML = '<i class="fa-solid fa-trash-can me-1"></i> Execute Cleanup';
                }
            })
            .catch(() => {
                retentionError.textContent = 'Server communication error.';
                executeBtn.disabled = false;
                executeBtn.innerHTML = '<i class="fa-solid fa-trash-can me-1"></i> Execute Cleanup';
            });
        });
    }

    // 4. Live Tail Streaming via Incremental Polling
    const toggleTailBtn = document.getElementById('toggleLiveTailBtn');
    const tailPanel = document.getElementById('liveTailPanel');
    const closeTailBtn = document.getElementById('closeTailBtn');
    const pauseResumeTailBtn = document.getElementById('pauseResumeTailBtn');
    const clearTailDisplayBtn = document.getElementById('clearTailDisplayBtn');
    const tailFeed = document.getElementById('liveTailFeed');
    const tailStatus = document.getElementById('tailStatus');
    const tailBtnText = document.getElementById('tailBtnText');
    const tailSpinner = document.getElementById('tailSpinner');

    let tailInterval = null;
    let currentOffset = {{ $selectedFile?->sizeInBytes ?? 0 }};
    let isTailPaused = false;

    function pollTail() {
        if (isTailPaused || !selectedFileId) return;

        tailStatus.textContent = 'Polling...';
        fetch(`{{ route("loglens.api.logs.tail") }}?file=${selectedFileId}&offset=${currentOffset}`)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    tailStatus.textContent = 'Active (Offset: ' + data.new_offset + ')';
                    currentOffset = data.new_offset;

                    if (data.entries && data.entries.length > 0) {
                        data.entries.forEach(entry => {
                            const lineDiv = document.createElement('div');
                            lineDiv.className = 'py-1 border-bottom border-secondary-subtle';
                            lineDiv.innerHTML = `<span class="badge bg-${entry.badge_class} text-uppercase me-2" style="font-size: 0.68rem;">${entry.severity}</span>` +
                                                `<span class="text-muted me-2">[${entry.timestamp}]</span>` +
                                                `<span class="text-light">${entry.message}</span>`;
                            tailFeed.appendChild(lineDiv);
                        });
                        tailFeed.scrollTop = tailFeed.scrollHeight;
                    }
                }
            })
            .catch(() => {
                tailStatus.textContent = 'Disconnected';
            });
    }

    if (toggleTailBtn && tailPanel) {
        toggleTailBtn.addEventListener('click', function () {
            if (tailPanel.classList.contains('d-none')) {
                // Open and start
                tailPanel.classList.remove('d-none');
                tailBtnText.textContent = 'Stop Tail';
                toggleTailBtn.classList.replace('btn-outline-primary', 'btn-primary');
                isTailPaused = false;
                tailInterval = setInterval(pollTail, 2000);
                pollTail();
            } else {
                // Stop
                stopTail();
            }
        });

        closeTailBtn.addEventListener('click', stopTail);

        function stopTail() {
            tailPanel.classList.add('d-none');
            tailBtnText.textContent = 'Live Tail';
            toggleTailBtn.classList.replace('btn-primary', 'btn-outline-primary');
            if (tailInterval) {
                clearInterval(tailInterval);
                tailInterval = null;
            }
        }

        pauseResumeTailBtn.addEventListener('click', function () {
            isTailPaused = !isTailPaused;
            if (isTailPaused) {
                this.innerHTML = '<i class="fa-solid fa-play me-1"></i> Resume';
                tailStatus.textContent = 'Paused';
                tailSpinner.classList.replace('text-danger', 'text-warning');
            } else {
                this.innerHTML = '<i class="fa-solid fa-pause me-1"></i> Pause';
                tailStatus.textContent = 'Active';
                tailSpinner.classList.replace('text-warning', 'text-danger');
                pollTail();
            }
        });

        clearTailDisplayBtn.addEventListener('click', function () {
            tailFeed.innerHTML = '<div class="text-muted text-center py-2">Buffer cleared. Listening for new entries...</div>';
        });
    }
});
</script>
@endpush
