<?php

return [

    /*
    |--------------------------------------------------------------------------
    | LogLens Master Switch
    |--------------------------------------------------------------------------
    |
    | This master switch determines whether LogLens monitoring and dashboard
    | routes are active. By default, monitoring is completely disabled to
    | prevent unintended instrumentation or resource usage in production.
    |
    */

    'enabled' => env('LOGLENS_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Dashboard Route Prefix & Domain
    |--------------------------------------------------------------------------
    |
    | This is the URI path where the LogLens dashboard will be accessible.
    | You may also configure a specific sub-domain if you prefer to host
    | the dashboard on an isolated administrative domain.
    |
    */

    'path' => env('LOGLENS_PATH', 'loglens'),

    'domain' => env('LOGLENS_DOMAIN', null),

    /*
    |--------------------------------------------------------------------------
    | Dashboard Middleware
    |--------------------------------------------------------------------------
    |
    | These middleware are assigned to every LogLens dashboard and API route.
    | 'web' enables session state and CSRF verification. 'loglens.auth'
    | ensures only authorized developers/administrators can view the dashboard.
    |
    */

    'middleware' => [
        'web',
        \MehrabHossain\LogLens\Http\Middleware\EnsureLogLensIsEnabled::class,
        \MehrabHossain\LogLens\Http\Middleware\AuthorizeLogLens::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Authorization Settings
    |--------------------------------------------------------------------------
    |
    | LogLens integrates with Laravel Gates. You can define access logic in
    | your AppServiceProvider or AuthServiceProvider via LogLens::auth() or
    | by registering the gates specified below.
    |
    | In 'local' environment, access is permitted by default unless configured.
    | In non-local environments, an explicit gate check or callback is required.
    |
    */

    'auth' => [
        'gate' => 'viewLogLens',
        'destructive_gate' => 'manageLogLens',
        'allow_local_by_default' => env('LOGLENS_ALLOW_LOCAL', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Directory & File Handling
    |--------------------------------------------------------------------------
    |
    | Define the directories from which LogLens can read log files.
    | For security reasons, path traversal outside these directories is
    | strictly prohibited, and symlinks pointing outside are rejected.
    |
    */

    'logs' => [
        'paths' => [
            storage_path('logs'),
        ],
        'allowed_extensions' => ['log'],
        'max_read_buffer_bytes' => 1048576, // 1MB buffer limit
        'max_file_read_lines' => 1000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Monitoring (Strict Zero-Extra-Query Mode)
    |--------------------------------------------------------------------------
    |
    | Strict monitoring mode observes existing Laravel database query events
    | (via DB::listen) without executing ANY additional queries on the
    | application database. Never insert rows per query event or poll DB.
    |
    */

    'database' => [
        'enabled' => env('LOGLENS_DB_MONITORING', true),
        'strict_mode' => true, // Enforce zero extra queries
        'observe_existing_queries' => true,
        'collect_bindings' => false, // Keep false for privacy & performance
        'slow_query_threshold_ms' => 500,
        'persist_metrics' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Request Performance Monitoring
    |--------------------------------------------------------------------------
    |
    | Instrument incoming HTTP requests to capture response times, status codes,
    | and throughput rates using bounded in-memory aggregation.
    |
    */

    'http' => [
        'enabled' => env('LOGLENS_HTTP_MONITORING', false),
        'slow_request_threshold_ms' => 1000,
        'ignore_paths' => [
            'loglens*',
            'telescope*',
            'horizon*',
            'pulse*',
            '_debugbar*',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Apache mod_status Integration
    |--------------------------------------------------------------------------
    |
    | Poll Apache's mod_status auto endpoint to track requests per second,
    | bytes per second, and active workers. Must run through a shared collector
    | rather than polling on every dashboard page view.
    |
    */

    'apache' => [
        'enabled' => env('LOGLENS_APACHE_MONITORING', false),
        'status_url' => env('LOGLENS_APACHE_STATUS_URL', 'http://127.0.0.1/server-status?auto'),
        'allowed_hosts' => [
            '127.0.0.1',
            'localhost',
            '::1',
        ],
        'collection_interval_seconds' => 1,
        'timeout_seconds' => 1.0,
        'stale_threshold_seconds' => 5,
        'history_samples' => 60,
        'cache_store' => env('LOGLENS_APACHE_CACHE_STORE', null), // null uses default cache store
        'cache_prefix' => 'loglens:apache',
        'ttl_seconds' => 3600,
        'dashboard_refresh_interval_ms' => 2000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Server Resource Monitoring (Linux / Host)
    |--------------------------------------------------------------------------
    |
    | Monitor CPU, RAM, Swap and Disk storage metrics using safe host inspections.
    | Disabled by default.
    |
    */

    'system' => [
        'enabled' => env('LOGLENS_SYSTEM_MONITORING', false),
        'disk_paths' => [
            'root' => '/',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Background Job Monitoring
    |--------------------------------------------------------------------------
    |
    | Observe processed and failed jobs, execution duration, and queue health.
    | Backlog polling is strictly opt-in to prevent unexpected queries.
    |
    */

    'queue' => [
        'enabled' => env('LOGLENS_QUEUE_MONITORING', false),
        'long_running_threshold_ms' => 5000,
        'poll_backlog' => false,
        'monitored_queues' => ['default'],
        'max_samples' => 50,
        // Worker liveness: idle workers write a heartbeat at most once per interval.
        // Raise the window if workers run with a large --sleep value.
        'heartbeat_interval_seconds' => 10,
        'heartbeat_window_seconds' => 60,
        // A job that started within this window keeps the worker "busy" (match your --timeout)
        'busy_window_seconds' => env('LOGLENS_QUEUE_BUSY_WINDOW', 900),
        // How many workers should be running (e.g. supervisor numprocs); null disables the check
        'expected_workers' => env('LOGLENS_QUEUE_EXPECTED_WORKERS'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Scheduler Task Monitoring
    |--------------------------------------------------------------------------
    |
    | Monitor execution duration, results, and overlap warnings for scheduled tasks.
    |
    */

    'scheduler' => [
        'enabled' => env('LOGLENS_SCHEDULER_MONITORING', false),
        'max_samples' => 50,
        // Cron runs schedule:run every minute; no heartbeat within this window means "off"
        'heartbeat_window_seconds' => 120,
    ],

    /*
    |--------------------------------------------------------------------------
    | Alert Evaluation & Thresholds
    |--------------------------------------------------------------------------
    |
    | Configurable thresholds for system health, HTTP performance, and queues.
    | Cooldown prevents notification spam for ongoing alert states.
    |
    */

    'alerts' => [
        'enabled' => env('LOGLENS_ALERTS_ENABLED', true),
        'cooldown_seconds' => 300,
        'thresholds' => [
            'cpu_percent' => 85,
            'memory_percent' => 90,
            'disk_percent' => 90,
            'slow_request_percent' => 10,
            'http_error_percent' => 5,
            'queue_failed_jobs' => 5,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Optional Redis Metrics Storage Backend
    |--------------------------------------------------------------------------
    */

    'redis' => [
        'connection' => env('LOGLENS_REDIS_CONNECTION', 'default'),
        'prefix' => 'loglens:metrics',
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrics Storage Backend
    |--------------------------------------------------------------------------
    |
    | Bounded memory is used by default without requiring database tables.
    | For shared multi-worker setups, 'redis' or external backends may be used.
    |
    */

    'metrics' => [
        'driver' => env('LOGLENS_METRICS_DRIVER', 'memory'), // 'memory' or 'redis'
        'retention_seconds' => 3600, // 1 hour buffer retention
        'max_samples' => 3600,
        'ttl' => env('LOGLENS_METRICS_TTL', 604800), // shared counter/history expiry (7 days)
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Retention & Cleanup Policies
    |--------------------------------------------------------------------------
    |
    | Automatic log deletion is strictly DISABLED by default to prevent data
    | loss. Cleanup actions require explicit authorization and dry-run preview.
    |
    */

    'cleanup' => [
        'auto_delete' => false, // Strictly disabled by default
        'retention_days' => 30,
        'archive_before_delete' => true,
        'dry_run' => true,
        'allow_destructive_actions' => env('LOGLENS_ALLOW_DESTRUCTIVE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security & Data Privacy
    |--------------------------------------------------------------------------
    |
    | Automatically redact sensitive information such as passwords, tokens,
    | secret keys, and credentials from logs, stack traces, and queries.
    |
    */

    'security' => [
        'redact_sensitive_data' => true,
        'sensitive_keys' => [
            'password',
            'password_confirmation',
            'secret',
            'token',
            'authorization',
            'bearer',
            'api_key',
            'apikey',
            'app_key',
            'key',
            'private_key',
            'credit_card',
            'cvv',
            'ssn',
        ],
    ],

];
