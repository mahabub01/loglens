# LogLens for Laravel 12

<p align="center">
  <strong>Developer-focused application log analyzer and monitoring dashboard for Laravel 12</strong><br>
  <em>Lightweight &bull; Modular &bull; Secure &bull; Zero Extra Database Queries</em>
</p>

---

## Overview

**LogLens** is an open-source, developer-first monitoring and log analyzer package built specifically for **Laravel 12** applications. It provides a graphical dashboard for inspecting logs, monitoring HTTP performance, observing database query events, analyzing Apache server health, and reviewing host system metrics.

### Non-Negotiable Database Principle

> [!IMPORTANT]
> **Strict Zero-Extra-Query Mode:** LogLens will **never** execute extra application database queries solely for monitoring. It observes existing Laravel query events passively (via `DB::listen`), never inserts a database row per query event, requires **zero database migrations**, and aggregates metrics in bounded memory or an external backend (e.g., Redis).

---

## Features & Implementation Status

| Feature / Module | Status | Description |
|---|---|---|
| **Phase 1: Package Foundation** | **Complete** | Service provider, configuration, routing, gates/callbacks, Bootstrap 5 UI, tests |
| **Phase 2: Log File Analyzer** | **Complete** | Bounded streaming reader, Monolog parser, severity filters, error grouping, live tail, safe cleanup |
| **Phase 3: Database & HTTP Metrics** | **Complete** | Passive `DB::listen` profiling, strict zero monitoring SQL, latency percentiles, N+1 detection |
| **Phase 4: Apache mod_status** | **Complete** | 1-second interval shared collector, RPS/BPS rates, busy/idle worker scoreboard |
| **Phase 5: Server Health & Queues** | **Complete** | OS resource probes (`/proc`, cgroups v1/v2), queue & scheduler lifecycle observation, Redis shared storage, alert foundation |
| **Phase 6: Production Hardening** | **Complete** | Security audit, performance benchmarks, zero-query validation, release checklists, SemVer 2.0 |

---

## Requirements

- **PHP:** `^8.1 || ^8.2 || ^8.3 || ^8.4`
- **Laravel Framework:** `^10.0 || ^11.0 || ^12.0 || ^13.0`
- **Frontend Stack:** Blade, Bootstrap 5, Font Awesome 6, Chart.js (No Tailwind CSS or Node/Inertia build steps required)
- **Database Migrations:** None required

---

## Installation

Install LogLens into your Laravel project via Composer:

```bash
composer require mehrabhossain/loglens
```

LogLens uses Laravel package auto-discovery. The `LogLensServiceProvider` and `LogLens` facade are automatically registered.

---

## Configuration & Publishing

Publish the configuration file:

```bash
php artisan vendor:publish --tag=loglens-config
```

Optionally, publish the Blade views to customize the dashboard interface:

```bash
php artisan vendor:publish --tag=loglens-views
```

### Master Switch & Safe Defaults

By default, monitoring is completely **disabled** to prevent accidental instrumentation or resource overhead:

```env
LOGLENS_ENABLED=false
```

To enable the dashboard in your local or staging environment, set:

```env
LOGLENS_ENABLED=true
LOGLENS_PATH=loglens
LOGLENS_ALLOW_DESTRUCTIVE=false
```

### Configuration Reference (`config/loglens.php`)

```php
return [
    // Master switch (disabled by default)
    'enabled' => env('LOGLENS_ENABLED', false),

    // Dashboard URI route path
    'path' => env('LOGLENS_PATH', 'loglens'),

    // Optional domain restriction (e.g., admin.example.com)
    'domain' => env('LOGLENS_DOMAIN', null),

    // Assigned route middleware
    'middleware' => [
        'web',
        \MehrabHossain\LogLens\Http\Middleware\EnsureLogLensIsEnabled::class,
        \MehrabHossain\LogLens\Http\Middleware\AuthorizeLogLens::class,
    ],

    // Authorization settings
    'auth' => [
        'gate' => 'viewLogLens',
        'destructive_gate' => 'manageLogLens',
        'allow_local_by_default' => env('LOGLENS_ALLOW_LOCAL', true),
    ],

    // Database monitoring: strict zero-extra-query mode
    'database' => [
        'enabled' => env('LOGLENS_DB_MONITORING', true),
        'strict_mode' => true,
        'observe_existing_queries' => true,
        'collect_bindings' => false,
        'slow_query_threshold_ms' => 500,
        'persist_metrics' => false,
    ],

    // Cleanup & retention: automatic deletion disabled by default
    'cleanup' => [
        'auto_delete' => false,
        'retention_days' => 30,
        'archive_before_delete' => true,
        'dry_run' => true,
        'allow_destructive_actions' => env('LOGLENS_ALLOW_DESTRUCTIVE', false),
    ],

    // Sensitive data redaction
    'security' => [
        'redact_sensitive_data' => true,
        'sensitive_keys' => [
            'password', 'secret', 'token', 'authorization', 'bearer',
            'api_key', 'app_key', 'key', 'private_key', 'credit_card',
        ],
    ],
];
```

---

## Authentication & Authorization

LogLens supports multiple authorization strategies to adapt to any Laravel user model or role system without forcing rigid database schemas.

### 1. Custom Authorization Callback (`LogLens::auth`)

In your `app/Providers/AppServiceProvider.php`:

```php
use MehrabHossain\LogLens\Facades\LogLens;

public function boot(): void
{
    LogLens::auth(function ($request) {
        return app()->environment('local') ||
               in_array($request->user()?->email, [
                   'lead-developer@example.com',
                   'devops@example.com',
               ]);
    });

    // Authorize destructive actions (e.g. log clearing)
    LogLens::destructiveAuth(function ($request) {
        return $request->user()?->email === 'lead-developer@example.com';
    });
}
```

### 2. Laravel Gate Definition

Alternatively, define standard Laravel Gates:

```php
use Illuminate\Support\Facades\Gate;

Gate::define('viewLogLens', function ($user) {
    return $user->hasPermissionTo('view-developer-tools');
});

Gate::define('manageLogLens', function ($user) {
    return $user->hasRole('super-admin');
});
```

### 3. Local Environment Behavior

In the `local` environment, access is permitted by default if `config('loglens.auth.allow_local_by_default')` is `true`. In non-local environments (`production`, `staging`), explicit authorization via Gate or `LogLens::auth()` callback is strictly required.

---

## Dashboard Navigation

When accessing `/loglens`, the responsive Bootstrap 5 dashboard includes:

- **Overview / Dashboard:** Application health, environment indicator, metric cards with real log stats, and Chart.js integration containers.
- **Logs (`/loglens/logs`):** Authorized log file browser, severity filters, keyword search, bounded pagination, expandable stack traces, copy message, and safe log clearing.
- **Errors (`/loglens/errors`):** Error fingerprinting dashboard, occurrence counts, first seen / last seen tracking, severity doughnut breakdown, and timeline trends.
- **HTTP Performance:** Request rate, average response times, p95/p99 latency distribution (Phase 3).
- **Database Queries:** Passive query duration tracking and slow query alerts with zero extra SQL (Phase 3).
- **Apache mod_status:** Real-time RPS, BPS, and worker status scoreboard (Phase 4).
- **Server Health:** Host CPU, RAM, and Disk health metrics (Phase 5).
- **Queue & Scheduler:** Background job status and failure rates (Phase 5).
- **Settings:** Active configuration inspection and permission auditing.

---

## Phase 2: Core Log Analyzer Guide

### 1. Bounded Streaming File Reader
LogLens never loads entire log files into memory. For large log files (hundreds of megabytes), it uses a reverse-chunk scanner that reads the most recent entries from the tail of the file backwards:
- Configurable maximum scan limit: `config('loglens.logs.max_read_buffer_bytes')` (default 5MB).
- Pagination: Reads 50 entries per page by default.
- Safety: Files that grow concurrently are read safely under binary read locks.

### 2. Monolog Parsing & Exception Detection
- Extracts standard Laravel Monolog headers: `[YYYY-MM-DD HH:MM:SS] channel.SEVERITY: Message {"context":...}`
- Automatically identifies exception class names (e.g. `QueryException`, `RuntimeException`).
- Folds multiline stack traces into clean expandable accordions.
- Automatically redacts sensitive fields (`password`, `token`, `secret`, `authorization`, `key`, etc.).

### 3. Error Fingerprinting & Grouping
The `ErrorFingerprinter` strips volatile tokens (UUIDs, database auto-increment IDs, IP addresses, dates, line numbers) to generate a deterministic fingerprint:
- Clustered errors show total occurrence counts, first seen, and last seen timestamps.
- Interactive Chart.js charts display timeline trends and severity distribution.

### 4. Live Log Tail
- Uses incremental polling via `GET /loglens/api/logs/tail?file={id}&offset={bytes}`.
- Tracks exact byte offsets so only newly appended lines are fetched.
- If log rotation or truncation occurs, offset is safely reset to 0.

### 5. Safe Log Cleanup & Retention
- **In-place Truncation:** `POST /loglens/api/logs/clear` truncates the file in place under an exclusive lock (`LOCK_EX`), keeping open file descriptors intact for PHP-FPM, Octane, and queue workers.
- **Dry-run Retention Preview:** `POST /loglens/api/cleanup/preview` reports aged daily log files without touching disk.
- **Confirmation & Authorization:** All destructive actions require `confirmed: true` and `manageLogLens` gate authorization. Silent automatic log deletion is strictly disabled by default.

---

## Phase 3: Zero-Extra-Query Database & HTTP Performance Monitoring

### 1. Strict Zero-Extra-Query Architecture
- **Passive Query Observation:** Listens strictly to Laravel's native `Illuminate\Database\Events\QueryExecuted` events via `DB::listen`.
- **Zero Monitoring SQL:** The listener **never** executes SQL queries, never queries `information_schema` or `SHOW STATUS`, and never inserts database records for monitoring.
- **Zero Migrations Required:** Metrics are stored entirely in bounded in-memory ring buffers (`MemoryMetricsStore`) with FIFO eviction, or via an external adapter.
- **Server Health Transparency:** Global database server metrics (buffer pool hits, thread cache) are explicitly documented as unavailable in strict mode rather than executing polling SQL.

### 2. HTTP Performance Middleware (`loglens.http`)
- Measures incoming request durations and response status code distribution (`2xx`, `3xx`, `4xx`, `5xx`).
- Computes statistical percentiles: **Average, Median (p50), p95, and p99 tail latency**.
- Flags slow requests exceeding `config('loglens.http.slow_request_threshold_ms')`.
- Safe exception handling: uncaught exceptions and aborted responses are captured and cleaned up gracefully.
- Ignores internal dashboard paths (`loglens*`, `telescope*`, etc.) by default.

### 3. Request Correlation & Context Propagation
- Resolves incoming `X-Correlation-ID` or `X-Request-ID` headers; generates safe UUIDs if absent.
- Validates external IDs against header injection and length constraints (4–64 chars, alphanumeric + dashes/underscores).
- Automatically attaches `X-Correlation-ID` to outgoing HTTP response headers.
- Propagates correlation IDs to Laravel's Monolog context via `Log::withContext()`.
- **Octane & Long-Running Worker Isolation:** Request state (`RequestContext` & `CorrelationIdManager`) is guaranteed to reset on termination, completely preventing state leakage across requests.

### 4. Database Query Fingerprinting & Sensitive Data Protection
- `QueryFingerprinter` strips literals, single/double quotes, numeric IDs, UUIDs, booleans, and `IN (...)` lists into canonical query fingerprints with 16-character SHA-256 hashes.
- Raw query bindings are **never captured by default** (`collect_bindings => false`).
- Sensitive parameter redaction automatically strips passwords, tokens, API keys, and authorization secrets.

### 5. Potential N+1 Query Detection
- Evaluates request-level query execution to detect repeated normalized query patterns (executed ≥ 3 times in a single request).
- Reports the query fingerprint, count, total duration, associated route, and optimization hint (e.g. recommending relationship eager loading via `with([...])`).
- Clearly labels detections as heuristic warnings rather than guaranteed diagnoses.

---

## Phase 4: Apache mod_status Live Monitoring

LogLens provides real-time, one-second sampling of Apache web server health metrics utilizing Apache's native `mod_status` module without placing redundant request load on your web server.

### 1. Zero-Redundant-Polling Architecture
- **Dedicated Collector Daemon:** Metric collection is completely decoupled from dashboard visitors. A background CLI worker (`php artisan loglens:apache-collect --interval=1`) samples `mod_status?auto` once per second.
- **Shared Cache Storage:** Snapshots, calculated rates, and historical ring buffers are saved to Laravel's cache (`redis`, `file`, or `memcached`).
- **Dashboard Isolation:** When 100 browser visitors view `/loglens/apache`, they read cached JSON snapshots from `/loglens/api/metrics/apache` — creating **zero direct requests** to Apache.
- **SSRF Protection:** Target URLs are validated against a strict allowed-hosts list (`127.0.0.1`, `localhost`, `::1`). Embedded credentials, non-HTTP schemes, and user-supplied URLs are strictly prohibited.

### 2. Supported Apache Metrics
- **Instantaneous Traffic Rates:** Consecutive delta calculations for Requests Per Second (RPS) and Transferred Bytes Per Second (BPS).
- **Worker Concurrency:** Busy workers (actively processing requests) and Idle workers (available capacity).
- **Lifetime Counters:** Total lifetime accesses, transferred kBytes/Bytes, and server uptime.
- **Worker Scoreboard Visualization:** Interactive breakdown of all worker slots:
  - `_` : Waiting for Connection (Idle)
  - `S` : Starting up
  - `R` : Reading Request
  - `W` : Sending Reply (Writing)
  - `K` : Keepalive (read)
  - `D` : DNS Lookup
  - `C` : Closing connection
  - `L` : Logging
  - `G` : Gracefully finishing
  - `I` : Idle cleanup of worker
  - `.` : Open slot with no current process

### 3. Rate Calculations & Anomaly Handling
- **Actual Elapsed Time:** Computes `RPS = delta_accesses / elapsed_seconds` using sub-second timestamps.
- **Server Restart Detection:** Automatically identifies when `uptime_current < uptime_previous` and suspends invalid delta calculations instead of presenting incorrect spikes or negative rates.
- **Counter Reset Detection:** Identifies counter rolls/resets (`accesses_current < accesses_previous`) without crashing.
- **Stale Metric Alerts:** If the background daemon halts or collection ceases, metrics are explicitly flagged with a warning banner when data age exceeds `config('loglens.apache.stale_threshold_seconds')` (default: 5s).

### 4. Apache Server Configuration Guide

> [!NOTE]
> LogLens never modifies your Apache configuration files automatically. Follow the standard configuration below to enable machine-readable `mod_status`.

#### Step 1: Enable `mod_status`
On Debian / Ubuntu:
```bash
sudo a2enmod status
sudo systemctl restart apache2
```
On RHEL / CentOS / Rocky Linux:
Ensure `LoadModule status_module modules/mod_status.so` is active in `httpd.conf`.

#### Step 2: Configure `/server-status` with Localhost Access Only
Add or verify the following configuration block (typically in `/etc/apache2/mods-available/status.conf` or `/etc/httpd/conf.d/status.conf`):

```apache
<IfModule mod_status.c>
    # Enable full detailed metrics (required for BPS and worker breakdown)
    ExtendedStatus On

    <Location /server-status>
        SetHandler server-status
        # Strictly restrict access to localhost only
        Require ip 127.0.0.1
        Require ip ::1
    </Location>
</IfModule>
```
Reload Apache:
```bash
sudo systemctl reload apache2  # or httpd
```

Verify that the machine-readable endpoint is reachable locally:
```bash
curl http://127.0.0.1/server-status?auto
```

### 5. Service Supervision (Running the Collector Daemon)

In development, run the collector directly:
```bash
php artisan loglens:apache-collect --interval=1
```

For production environments, run the collector under a process supervisor:

#### Option A: Supervisord Configuration (`/etc/supervisor/conf.d/loglens-apache.conf`)
```ini
[program:loglens-apache]
command=php artisan loglens:apache-collect --interval=1
directory=/var/www/html
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/loglens-apache.log
```

#### Option B: Systemd Service Unit (`/etc/systemd/system/loglens-apache.service`)
```ini
[Unit]
Description=LogLens Apache Status Collector Daemon
After=network.target apache2.service

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/html
ExecStart=/usr/bin/php artisan loglens:apache-collect --interval=1
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
```
Enable and start the service:
```bash
sudo systemctl enable --now loglens-apache.service
```

### 6. Multi-Instance & Distributed Deployments
For deployments with multiple load-balanced web servers:
1. Configure `LOGLENS_APACHE_CACHE_STORE=redis` so all application instances share the same metrics snapshot in Redis.
2. Run one collector daemon per Apache node, or point the collector to the local node's `http://127.0.0.1/server-status?auto`.

---

## Phase 5: Server Health, Queue & Scheduler Monitoring

LogLens provides lightweight, safe monitoring of host operating system resources, background queue worker processing, console scheduler executions, and threshold alert evaluations.

### 1. Operating System & Host Resource Metrics

The `SystemMetricsCollector` collects host metrics using safe native operating system interfaces without executing arbitrary shell commands using dashboard input:

- **CPU Utilization:** Calculated via delta differences in `/proc/stat` (user, nice, system, idle, iowait).
- **Load Average:** Probes `sys_getloadavg()` or `/proc/loadavg` (1m, 5m, 15m).
- **Memory & Swap:**
  - **Linux Bare Metal / VM:** Host `/proc/meminfo` (MemTotal, MemFree, MemAvailable, Buffers, Cached, SwapTotal, SwapFree).
  - **Linux Containers (cgroups v2):** Container limits from `/sys/fs/cgroup/memory.max` and usage from `memory.current`.
  - **Linux Containers (cgroups v1):** Container limits from `memory.limit_in_bytes` and usage from `memory.usage_in_bytes`.
  - **Non-Linux / Restricted:** Process memory via `memory_get_usage()` with missing host metrics explicitly reported as `null` (never fabricated as zero).
- **Disk Allocation:** Probes filesystem mount points using `disk_total_space()` and `disk_free_space()`.
- **Log Storage Footprint:** Calculates total disk size and file count across configured log paths.
- **Network I/O:** Probes cumulative bytes transferred from `/proc/net/dev`.

#### Container-Specific Limitations

> [!NOTE]
> **Container Memory & Resource Isolation:** In containerized environments (Docker, Kubernetes OCI, ECS), `/proc/meminfo` reflects the *host machine's* physical RAM rather than container quotas. LogLens automatically detects Docker (`/.dockerenv`, `/proc/1/cgroup`) and inspects cgroups v1 or v2 controller files to report the true container memory limit and usage. If no cgroup memory limits are assigned to the container, it reports the physical host memory.

### 2. Queue Monitoring (Strict Zero-Extra-Query)

Queue monitoring observes native Laravel queue events (`JobProcessing`, `JobProcessed`, `JobFailed`) without creating database queries per dashboard refresh:

- **Processed Jobs:** Successful background job counter.
- **Failed Jobs:** Real-time failed job tracking with full exception class and error message.
- **Job Duration:** Millisecond-precision execution timing and average run duration.
- **Long-Running Job Detection:** Configurable threshold (`long_running_threshold_ms`, default 5000ms) flags slow jobs.
- **Backlog Polling (Strictly Opt-In):** `Queue::size()` backlog polling is disabled by default (`poll_backlog => false`) to preserve zero extra database queries in strict mode.

Enable in `.env`:
```env
LOGLENS_QUEUE_MONITORING=true
LOGLENS_QUEUE_POLL_BACKLOG=false
```

### 3. Scheduler Monitoring

Observes Laravel console schedule events (`ScheduledTaskStarting`, `ScheduledTaskFinished`, `ScheduledTaskFailed`, `ScheduledTaskSkipped`):

- **Execution Results:** Success, failure, and execution duration per task.
- **Last Observed Activity:** Timestamps of actual observed task executions (no fabricated next execution claims).
- **Overlap Warnings:** Tasks skipped due to `withoutOverlapping()` mutex locks are explicitly flagged with `Skipped (Overlap)` warnings.

Enable in `.env`:
```env
LOGLENS_SCHEDULER_MONITORING=true
```

### 4. Optional Shared Storage & Redis Backend

For multi-worker or horizontally scaled clusters, LogLens includes an optional `RedisMetricsStore`:

- **Atomic Shared Counters:** Multi-worker aggregation via Redis `INCRBY`.
- **Bounded Sliding History:** Metric history capped via Redis `LPUSH` and `LTRIM` with bounded retention.
- **Collector Heartbeats:** Nodes report heartbeats; stale or disconnected nodes are flagged after threshold expiry (`stale_threshold_seconds`).
- **Fail-Safe Fallback:** If Redis is unreachable, LogLens reports the backend as unavailable and operates gracefully without crashing application requests.

Configure in `.env`:
```env
LOGLENS_METRICS_DRIVER=redis
LOGLENS_REDIS_CONNECTION=default
LOGLENS_REDIS_PREFIX=loglens:metrics
```

### 5. Alert Foundation & Deduplication

Configurable thresholds monitor key system, HTTP, and background queue health indicators:

```php
'alerts' => [
    'enabled' => env('LOGLENS_ALERTS_ENABLED', true),
    'cooldown_seconds' => 300, // Suppress duplicate notifications for 5 minutes
    'thresholds' => [
        'cpu_percent' => 85,
        'memory_percent' => 90,
        'disk_percent' => 90,
        'slow_request_percent' => 10,
        'http_error_percent' => 5,
        'queue_failed_jobs' => 5,
    ],
],
```

Alerts include cooldown deduplication (`in_cooldown` flag) to prevent repeated notification spam for ongoing alert states.

---

## Testing & Quality Assurance

LogLens includes an automated test suite executed with PHPUnit and Orchestra Testbench.

Run tests:

```bash
composer test
```

Or run PHPUnit directly:

```bash
./vendor/bin/phpunit --testdox
```

### Test Coverage

The test suite contains **202 tests and 872 assertions** covering:

- **Service Provider Registration:** Container bindings, route registrations, and singleton service resolution.
- **Configuration Defaults:** Safe defaults (disabled by default, automatic deletion off, strict DB mode on).
- **Configuration & Views Publishing:** `artisan vendor:publish` execution.
- **Route Registration:** Prefix and name resolution.
- **Authentication & Authorization:** 403 on unauthorized requests, Gate validation, custom `LogLens::auth()` callback.
- **Disabled Package Handling:** Returns 404 when `loglens.enabled` is `false`.
- **Dashboard Rendering:** Bootstrap 5 UI validation, Font Awesome icons, exclusion of Tailwind CSS, environment indicators.
- **CSRF & Destructive Operation Security:** State-changing endpoints require CSRF verification, explicit confirmation flag, and destructive authorization.
- **Strict Zero-Database-Query Verification:** Confirms that rendering the dashboard, queue, scheduler, and system routes executes **0** database queries on the application database.
- **HTTP Latency & Percentiles:** Statistical percentiles (Median, p95, p99), error code distribution buckets, and slow request thresholds.
- **Database Query Observers & N+1 Heuristics:** Zero-extra-query event listening, query fingerprinting, and potential N+1 query pattern detection.
- **Apache mod_status Live Monitoring:** Machine-readable parsing, consecutive rate calculations (RPS, BPS), server restart / counter reset detection, SSRF protection, and shared daemon cache isolation (0 direct Apache requests from dashboard visitors).
- **Platform Detection & Containers:** Linux host, cgroups v1 / cgroups v2 container memory parsing, Windows development fallback (missing host metrics reported as `null`, never `0`).
- **Queue Events & Background Jobs:** `JobProcessing`, `JobProcessed`, `JobFailed`, duration tracking, long-running job detection, failed job traces.
- **Scheduler Events & Overlap Detection:** `ScheduledTaskStarting`, `ScheduledTaskFinished`, `ScheduledTaskFailed`, `ScheduledTaskSkipped` with mutex overlap detection.
- **Redis Shared Storage:** Atomic multi-worker aggregation, bounded sliding history buffers, stale heartbeat detection, graceful disconnection handling without application crashes.
- **Alert Foundation:** Threshold evaluation across CPU, memory, disk, HTTP 5xx errors, slow requests, and queue failures with cooldown deduplication.
- **Performance & Scalability Benchmarks:** Validated execution time, memory stability under 1,000 long-running worker cycles, and high query event throughput (>80,000 queries/sec).

---

## Performance & Scalability Benchmarks

The following benchmarks were measured using [`tests/Performance/PerformanceBenchmarkTest.php`](tests/Performance/PerformanceBenchmarkTest.php) under PHP 8.3:

| Metric / Scenario | Measured Result | Performance Assessment |
|---|---|---|
| **Query Event Handling** | **10.97 µs** per event (**91,141 queries/sec**) | Near-zero overhead on `DB::listen` |
| **HTTP Request Collection** | **64.04 µs** per request (**15,615 req/sec**) | Negligible impact on HTTP response cycle |
| **Statistical Percentile Calculation** | **0.38 ms** (Median, p95, p99 across 2,000 samples) | Fast in-memory statistical ranking |
| **Rapid Log Streaming** | **17.17 ms** for 1,000 lines (**58,234 lines/sec**) | Fast non-blocking incremental tail |
| **Large Log Parsing** | **10.80 ms** (10,000-line file / 1.1 MB) | Zero process RAM growth (bounded buffer) |
| **Worker RAM Stability** | **0.00 MB growth** over 1,000 job cycles | Zero memory leak in long-running processes |
| **Dashboard Response Latency** | **2.2 ms – 5.5 ms** across all views | Sub-10ms warm cache dashboard render |

---

## Production Deployment Checklist

Before deploying LogLens to a production Laravel environment:

1. [ ] **Authorization Configured:** Register `LogLens::auth()` in `AppServiceProvider` or verify Gate `viewLogLens` is restricted to authorized administrative users.
2. [ ] **Destructive Actions Restricted:** Ensure `LOGLENS_ALLOW_DESTRUCTIVE=false` in `.env` unless administrative log file truncation is explicitly authorized via `LogLens::destructiveAuth()`.
3. [ ] **Allowed Apache Hosts:** If using Apache live monitoring, verify `LOGLENS_APACHE_STATUS_URL` points to `http://127.0.0.1/server-status?auto` and `LOGLENS_APACHE_ALLOWED_HOSTS` contains only local addresses.
4. [ ] **Queue Backlog Strict Mode:** Leave `LOGLENS_QUEUE_POLL_BACKLOG=false` to ensure zero database queries on queue monitoring dashboards.
5. [ ] **Redis Prefix Isolation:** If multiple apps share a Redis instance, configure distinct `LOGLENS_REDIS_PREFIX=myapp:loglens:metrics`.
6. [ ] **Alert Thresholds:** Adjust CPU, Memory, Disk, and HTTP error rate alert thresholds in `config/loglens.php` to match server capacity.
7. [ ] **Log File Permissions:** Ensure web server worker user (`www-data` or `nginx`) has read permissions on `storage/logs/`.

---

## Semantic Versioning Strategy

LogLens strictly follows [Semantic Versioning 2.0.0](https://semver.org/):
- **Major (X.0.0):** Breaking changes to public interfaces (`Contracts`), removal of supported PHP or Laravel versions, or major schema/configuration restructurings.
- **Minor (1.X.0):** Backwards-compatible feature additions (new collectors, exporters, dashboard tabs) without breaking existing interfaces.
- **Patch (1.0.X):** Backwards-compatible bug fixes, security patches, parser optimizations, or documentation corrections.

---

## Security Considerations

1. **Path Traversal Prevention:** File access is restricted strictly to configured paths (`config('loglens.logs.paths')`). Symlinks pointing outside designated directories are rejected.
2. **Never Chmod 777:** LogLens runs under normal web server user permissions and never requires broad permissions.
3. **No Arbitrary Commands or SQL:** The dashboard does not accept or execute arbitrary shell commands or SQL statements.
4. **Data Redaction:** Sensitive keys (passwords, tokens, authorization headers) are filtered from logs and query profiles.
5. **CSRF Protection:** All state-changing routes enforce Laravel CSRF protection.

---

## License

LogLens is open-sourced software licensed under the [MIT license](LICENSE).
