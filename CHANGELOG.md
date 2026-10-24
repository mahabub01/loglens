# Changelog

All notable changes to `mehrabhossain/loglens` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.2.0] - 2026-10-24

### Added
- **Queue worker list.** The Queue page lists every live `queue:work` process (`hostname:pid`), with its connection and queue, idle or busy status, current job and how long it has been running, jobs processed and failed, memory and uptime. `getSummary()` exposes `workers`, `workers_online` and `expected_workers`.
  - Each worker writes its own state key, and a registry set lists the worker ids (a Redis sorted set, or a lock-guarded map on cache stores). A worker that stops cleanly (`WorkerStopping`) is removed immediately. A crashed worker disappears once its heartbeat window expires.
  - Optional `loglens.queue.expected_workers` (`LOGLENS_QUEUE_EXPECTED_WORKERS`) shows an "N / M Online" warning when fewer workers are running than expected.
- `SharedMetricsStorageInterface::touchMember()`, `getMembers()` and `removeMember()` for registry sets.

### Fixed
- `active_jobs_count` was always 0 on the dashboard. It now counts busy workers across all worker processes.
- With several workers, one worker finishing a job could clear another worker's busy state and briefly show the queue as Off. Busy state is now tracked per worker.
- Jobs run inline by the `sync` driver no longer make the queue look "running", because only processes that run the worker loop register as workers.

---

## [1.1.0] - 2026-10-24

### Fixed
- **Queue & Scheduler dashboards always showed 0 / "Waiting".** `QueueMetricsCollector` and `SchedulerMetricsCollector` kept metrics in per-process memory, so events recorded inside `queue:work` and `schedule:run` never reached the dashboard's web process. Both collectors now write counters, durations, last-run time and recent history to the shared metrics store (`SharedMetricsStorageInterface`), so any process can read them.
- `CacheMetricsStore::increment()` is now atomic (`Cache::add()` + `Cache::increment()`), so counts are no longer lost when several queue workers run concurrently.
- Shared counters and history no longer silently reset every 24 hours. Their expiry is now configurable via `loglens.metrics.ttl` (`LOGLENS_METRICS_TTL`, default 7 days).
- Storage failures inside the queue and scheduler collectors are swallowed and can never interrupt jobs or scheduled tasks.

### Added
- `SharedMetricsStorageInterface::forget()` for removing a value and its history list (implemented by `CacheMetricsStore` and `RedisMetricsStore`).
- **Scheduler & queue worker running/off status.** Both pages show a status badge in the header, and `getSummary()` exposes `status`, `is_alive` and `last_heartbeat_at`.
  - Scheduler: a heartbeat is recorded whenever `schedule:run` / `schedule:work` starts (`CommandStarting`), even when no task is due. No heartbeat within `loglens.scheduler.heartbeat_window_seconds` (120s) means **Off**.
  - Queue: idle workers record a heartbeat from the `Looping` event, throttled to once per `loglens.queue.heartbeat_interval_seconds` (10s) per worker. No heartbeat within `loglens.queue.heartbeat_window_seconds` (60s) means **Off**.
  - A worker inside a long job stops looping, so `JobProcessing` sets a busy marker (cleared when the job finishes). The worker then shows **Running (busy)** instead of Off, for up to `loglens.queue.busy_window_seconds` (900s).
- `SharedMetricsStorageInterface::recordHeartbeat()` accepts an optional `$ttlSeconds` argument (default `60`).

---

## [1.0.0] - 2026-10-17

### Added
- **Phase 1: Package Foundation & Core Architecture**
  - Laravel 12 compatible service provider (`LogLensServiceProvider`) with auto-discovery.
  - Multi-tiered authorization architecture supporting `LogLens::auth()` and `LogLens::destructiveAuth()` callbacks alongside Laravel Gates (`viewLogLens`, `manageLogLens`).
  - Zero-build frontend layout using Blade, Bootstrap 5.3, Font Awesome 6.6, and Chart.js 4.4 without Tailwind CSS or Node build dependencies.
  - Master package switch (`LOGLENS_ENABLED=false` by default).

- **Phase 2: Log File Analyzer & Safe Management**
  - Streaming, bounded log reader (`BoundedFileReader`) capable of scanning large log files (>100MB) without memory exhaustion.
  - Monolog 3 parser supporting standard Laravel channel formats, multi-line stack traces, and severity filtering (DEBUG, INFO, NOTICE, WARNING, ERROR, CRITICAL, ALERT, EMERGENCY).
  - Deterministic error fingerprinter (`ErrorFingerprinter`) for grouping recurring errors.
  - Real-time incremental live tail streaming via SSE/JSON polling.
  - Safe log cleanup service (`LogCleanupService`) with dry-run preview, atomic truncation, and retention deletion requiring multi-layer destructive authorization.

- **Phase 3: Database & HTTP Metrics (Strict Zero-Extra-Query)**
  - Passive database query observation via `DB::listen` with strict zero additional monitoring queries.
  - Query fingerprinter (`QueryFingerprinter`) normalizing SQL literals and stripping parameter bindings.
  - Automatic N+1 query heuristic detection.
  - HTTP middleware (`LogLensHttpMonitoringMiddleware`) capturing response durations and status code distribution.
  - Correlation ID manager (`CorrelationIdManager`) tracking request lifecycles.
  - Statistical latency calculations (Median, p95, p99) computed in bounded memory.

- **Phase 4: Apache Live Monitoring**
  - Dedicated background collector daemon (`ApacheStatusCollector` via `artisan loglens:apache-collect`).
  - Safe parsing of Apache `mod_status?auto` output.
  - Consecutive rate calculator (`ApacheRateCalculator`) computing requests/sec and bytes/sec with restart and counter-reset detection.
  - SSRF protection validating allowed local hosts.
  - Zero-query shared cache store (`CacheApacheMetricsStore`) isolating dashboard visitors from direct Apache polling.

- **Phase 5: Server Health, Queue & Scheduler Monitoring**
  - Safe OS resource collector (`SystemMetricsCollector`) reading `/proc/stat`, `/proc/meminfo`, `/proc/net/dev`, and filesystem mounts.
  - Platform detector (`PlatformDetector`) detecting Linux bare metal, containers (Docker, cgroups v1 & v2), Windows development environments, and restricted hosts.
  - Passive queue observer (`QueueMetricsCollector`) tracking jobs processed, failed, duration, and long-running job alerts with zero extra DB queries.
  - Console scheduler observer (`SchedulerMetricsCollector`) tracking task executions, run durations, and `withoutOverlapping` mutex overlap warnings.
  - Alert manager (`AlertManager`) with configurable thresholds and cooldown deduplication.
  - Optional Redis metrics storage (`RedisMetricsStore`) supporting atomic increments, sliding history (`LPUSH`/`LTRIM`), and collector heartbeats with fail-safe error handling.

- **Phase 6: Production Hardening & Benchmarking**
  - Comprehensive performance benchmark suite verifying streaming throughput (>60,000 lines/sec) and sub-millisecond query capture overhead (<15µs).
  - Automated security tests verifying zero database queries on all dashboard routes, strict CSRF validation, and path traversal rejection.
  - Long-running worker memory stability verification (0.00 MB memory growth over 1,000 job cycles).
