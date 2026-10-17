<header class="ll-topbar d-flex align-items-center justify-content-between">
    <!-- Left: Mobile Toggle & Breadcrumbs -->
    <div class="d-flex align-items-center gap-3">
        <!-- Mobile Sidebar Button -->
        <button class="btn btn-outline-secondary btn-sm d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar" aria-controls="mobileSidebar">
            <i class="fa-solid fa-bars"></i>
        </button>

        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0" style="font-size: 0.85rem;">
                <li class="breadcrumb-item"><a href="{{ route('loglens.dashboard') }}" class="text-decoration-none text-muted">LogLens</a></li>
                <li class="breadcrumb-item active text-light" aria-current="page">{{ $title ?? 'Dashboard' }}</li>
            </ol>
        </nav>
    </div>

    <!-- Right: Environment Badge & Metadata -->
    <div class="d-flex align-items-center gap-3">
        <!-- Environment Badge -->
        @php
            $env = app()->environment();
            $envClass = match($env) {
                'production' => 'bg-danger text-white',
                'staging' => 'bg-warning text-dark',
                'local', 'development' => 'bg-info-subtle text-info border border-info-subtle',
                default => 'bg-secondary text-white',
            };
        @endphp
        <span class="badge {{ $envClass }} ll-badge-pill text-uppercase">
            <i class="fa-solid fa-circle me-1" style="font-size: 0.5rem; vertical-align: middle;"></i>
            {{ strtoupper($env) }}
        </span>

        <!-- System Pill -->
        <div class="d-none d-md-flex align-items-center gap-2 text-muted small">
            <span><i class="fa-brands fa-php text-primary"></i> {{ PHP_VERSION }}</span>
            <span>&bull;</span>
            <span>Laravel {{ app()->version() }}</span>
        </div>

        <!-- Monitoring Switch Status -->
        <span class="badge bg-success-subtle text-success border border-success-subtle ll-badge-pill d-none d-sm-inline-block">
            <i class="fa-solid fa-shield-halved me-1"></i> Foundation Active
        </span>
    </div>
</header>
