<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ?? 'Dashboard' }} - LogLens Monitoring</title>

    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">

    <!-- LogLens Custom Theme & Tokens (No Tailwind) -->
    <style>
        :root {
            --ll-body-bg: #0b1120;
            --ll-sidebar-bg: #0f172a;
            --ll-card-bg: #1e293b;
            --ll-card-border: #334155;
            --ll-text-main: #f8fafc;
            --ll-text-muted: #94a3b8;
            --ll-accent: #3b82f6;
            --ll-accent-hover: #2563eb;
            --ll-success: #10b981;
            --ll-warning: #f59e0b;
            --ll-danger: #ef4444;
            --ll-info: #06b6d4;
        }

        body {
            background-color: var(--ll-body-bg);
            color: var(--ll-text-main);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            min-height: 100vh;
        }

        .ll-sidebar {
            width: 260px;
            background-color: var(--ll-sidebar-bg);
            border-right: 1px solid var(--ll-card-border);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
        }

        .ll-nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 16px;
            color: var(--ll-text-muted);
            text-decoration: none;
            border-radius: 8px;
            margin: 3px 12px;
            font-size: 0.925rem;
            font-weight: 500;
            transition: all 0.2s ease-in-out;
        }

        .ll-nav-link:hover {
            color: var(--ll-text-main);
            background-color: rgba(255, 255, 255, 0.05);
        }

        .ll-nav-link.active {
            color: #ffffff;
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.25), rgba(99, 102, 241, 0.25));
            border-left: 3px solid var(--ll-accent);
        }

        .ll-nav-link i {
            width: 20px;
            text-align: center;
            font-size: 1.05rem;
        }

        .ll-card {
            background-color: var(--ll-card-bg);
            border: 1px solid var(--ll-card-border);
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.15), 0 2px 4px -2px rgba(0, 0, 0, 0.15);
            transition: transform 0.2s ease, border-color 0.2s ease;
        }

        .ll-card:hover {
            border-color: rgba(59, 130, 246, 0.5);
        }

        .ll-stat-title {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--ll-text-muted);
            font-weight: 600;
        }

        .ll-stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--ll-text-main);
            margin: 6px 0 4px;
        }

        .ll-topbar {
            background-color: var(--ll-sidebar-bg);
            border-bottom: 1px solid var(--ll-card-border);
            padding: 12px 24px;
        }

        .ll-badge-pill {
            font-size: 0.72rem;
            letter-spacing: 0.03em;
            padding: 4px 10px;
            border-radius: 20px;
            font-weight: 600;
        }

        .ll-chart-box {
            position: relative;
            min-height: 250px;
        }

        .ll-placeholder-overlay {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(2px);
            border-radius: 8px;
            z-index: 5;
        }
    </style>
    @stack('styles')
</head>
<body>
<div class="d-flex">
    <!-- Sidebar Navigation -->
    @include('loglens::partials.sidebar')

    <!-- Main Content Area -->
    <div class="flex-grow-1 d-flex flex-column min-vh-100">
        <!-- Topbar Header -->
        @include('loglens::partials.header')

        <!-- Main Body -->
        <main class="flex-grow-1 p-4">
            @yield('content')
        </main>

        <!-- Footer -->
        <footer class="py-3 px-4 text-center border-top text-muted" style="border-color: var(--ll-card-border) !important; font-size: 0.85rem;">
            <span>LogLens v{{ \MehrabHossain\LogLens\LogLens::VERSION }} &bull; Developer Log & Performance Monitoring for Laravel 12 &bull; Zero-Extra-Query Guaranteed</span>
        </footer>
    </div>
</div>

<!-- Bootstrap 5 Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Chart.js 4.4 -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>

<script>
    window.LogLens = {!! json_encode(\MehrabHossain\LogLens\LogLens::scriptVariables()) !!};
</script>
@stack('scripts')
</body>
</html>
