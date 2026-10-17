<aside class="ll-sidebar d-none d-lg-flex" id="loglensSidebar">
    <!-- Brand Header -->
    <div class="p-3 border-bottom d-flex align-items-center justify-content-between" style="border-color: var(--ll-card-border) !important;">
        <a href="{{ route('loglens.dashboard') }}" class="d-flex align-items-center gap-2 text-decoration-none text-light">
            <span class="fs-4 text-primary"><i class="fa-solid fa-magnifying-glass-chart"></i></span>
            <span class="fs-5 fw-bold tracking-tight">LogLens</span>
        </a>
        <span class="badge bg-primary-subtle text-primary border border-primary-subtle ll-badge-pill">
            v{{ \MehrabHossain\LogLens\LogLens::VERSION }}
        </span>
    </div>

    <!-- Navigation Menu -->
    <div class="py-3 flex-grow-1 overflow-y-auto">
        <div class="px-3 mb-2 text-uppercase text-muted fw-semibold" style="font-size: 0.72rem; letter-spacing: 0.08em;">
            Core Observability
        </div>

        <a href="{{ route('loglens.dashboard') }}" class="ll-nav-link {{ request()->routeIs('loglens.dashboard') ? 'active' : '' }}">
            <i class="fa-solid fa-gauge-high"></i>
            <span>Overview</span>
        </a>

        <a href="{{ route('loglens.logs.index') }}" class="ll-nav-link {{ request()->routeIs('loglens.logs.*') ? 'active' : '' }}">
            <i class="fa-solid fa-file-lines"></i>
            <span>Logs</span>
        </a>

        <a href="{{ route('loglens.errors.index') }}" class="ll-nav-link {{ request()->routeIs('loglens.errors.*') ? 'active' : '' }}">
            <i class="fa-solid fa-triangle-exclamation text-warning"></i>
            <span>Errors</span>
        </a>

        <div class="px-3 mt-3 mb-2 text-uppercase text-muted fw-semibold" style="font-size: 0.72rem; letter-spacing: 0.08em;">
            Performance & Health
        </div>

        <a href="{{ route('loglens.http.index') }}" class="ll-nav-link {{ request()->routeIs('loglens.http.*') ? 'active' : '' }}">
            <i class="fa-solid fa-bolt-lightning text-info"></i>
            <span>HTTP</span>
        </a>

        <a href="{{ route('loglens.database.index') }}" class="ll-nav-link {{ request()->routeIs('loglens.database.*') ? 'active' : '' }}">
            <i class="fa-solid fa-database text-success"></i>
            <span>Database</span>
        </a>

        <a href="{{ route('loglens.apache.index') }}" class="ll-nav-link {{ request()->routeIs('loglens.apache.*') ? 'active' : '' }}">
            <i class="fa-solid fa-server text-danger"></i>
            <span>Apache</span>
        </a>

        <a href="{{ route('loglens.server.index') }}" class="ll-nav-link {{ request()->routeIs('loglens.server.*') ? 'active' : '' }}">
            <i class="fa-solid fa-heart-pulse text-primary"></i>
            <span>Server Health</span>
        </a>

        <a href="{{ route('loglens.queue.index') }}" class="ll-nav-link {{ request()->routeIs('loglens.queue.*') ? 'active' : '' }}">
            <i class="fa-solid fa-clock-rotate-left"></i>
            <span>Queue</span>
        </a>

        <a href="{{ route('loglens.scheduler.index') }}" class="ll-nav-link {{ request()->routeIs('loglens.scheduler.*') ? 'active' : '' }}">
            <i class="fa-solid fa-calendar-check"></i>
            <span>Scheduler</span>
        </a>

        <div class="px-3 mt-3 mb-2 text-uppercase text-muted fw-semibold" style="font-size: 0.72rem; letter-spacing: 0.08em;">
            System
        </div>

        <a href="{{ route('loglens.settings.index') }}" class="ll-nav-link {{ request()->routeIs('loglens.settings.*') ? 'active' : '' }}">
            <i class="fa-solid fa-sliders"></i>
            <span>Settings</span>
        </a>
    </div>

    <!-- Sidebar Status Card -->
    <div class="p-3 border-top" style="border-color: var(--ll-card-border) !important;">
        <div class="p-2 rounded bg-dark border border-secondary-subtle">
            <div class="d-flex align-items-center justify-content-between mb-1">
                <span class="small fw-semibold text-light">DB Monitor</span>
                <span class="badge bg-success-subtle text-success ll-badge-pill">Zero-Query Safe</span>
            </div>
            <div class="text-muted" style="font-size: 0.75rem;">
                No DB writes or overhead
            </div>
        </div>
    </div>
</aside>

<!-- Mobile Offcanvas Sidebar -->
<div class="offcanvas offcanvas-start bg-dark text-white d-lg-none" tabindex="-1" id="mobileSidebar" aria-labelledby="mobileSidebarLabel">
    <div class="offcanvas-header border-bottom border-secondary">
        <h5 class="offcanvas-title d-flex align-items-center gap-2" id="mobileSidebarLabel">
            <i class="fa-solid fa-magnifying-glass-chart text-primary"></i>
            <span>LogLens</span>
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-0">
        <div class="py-2">
            <a href="{{ route('loglens.dashboard') }}" class="ll-nav-link {{ request()->routeIs('loglens.dashboard') ? 'active' : '' }}">
                <i class="fa-solid fa-gauge-high"></i> Overview
            </a>
            <a href="{{ route('loglens.logs.index') }}" class="ll-nav-link {{ request()->routeIs('loglens.logs.*') ? 'active' : '' }}">
                <i class="fa-solid fa-file-lines"></i> Logs
            </a>
            <a href="{{ route('loglens.errors.index') }}" class="ll-nav-link {{ request()->routeIs('loglens.errors.*') ? 'active' : '' }}">
                <i class="fa-solid fa-triangle-exclamation text-warning"></i> Errors
            </a>
            <a href="{{ route('loglens.http.index') }}" class="ll-nav-link {{ request()->routeIs('loglens.http.*') ? 'active' : '' }}">
                <i class="fa-solid fa-bolt-lightning text-info"></i> HTTP
            </a>
            <a href="{{ route('loglens.database.index') }}" class="ll-nav-link {{ request()->routeIs('loglens.database.*') ? 'active' : '' }}">
                <i class="fa-solid fa-database text-success"></i> Database
            </a>
            <a href="{{ route('loglens.apache.index') }}" class="ll-nav-link {{ request()->routeIs('loglens.apache.*') ? 'active' : '' }}">
                <i class="fa-solid fa-server text-danger"></i> Apache
            </a>
            <a href="{{ route('loglens.server.index') }}" class="ll-nav-link {{ request()->routeIs('loglens.server.*') ? 'active' : '' }}">
                <i class="fa-solid fa-heart-pulse text-primary"></i> Server Health
            </a>
            <a href="{{ route('loglens.queue.index') }}" class="ll-nav-link {{ request()->routeIs('loglens.queue.*') ? 'active' : '' }}">
                <i class="fa-solid fa-clock-rotate-left"></i> Queue
            </a>
            <a href="{{ route('loglens.scheduler.index') }}" class="ll-nav-link {{ request()->routeIs('loglens.scheduler.*') ? 'active' : '' }}">
                <i class="fa-solid fa-calendar-check"></i> Scheduler
            </a>
            <a href="{{ route('loglens.settings.index') }}" class="ll-nav-link {{ request()->routeIs('loglens.settings.*') ? 'active' : '' }}">
                <i class="fa-solid fa-sliders"></i> Settings
            </a>
        </div>
    </div>
</div>
