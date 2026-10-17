# Changelog

All notable changes to `mehrabhossain/loglens` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
