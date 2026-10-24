# Upgrade Guide

This guide describes how to upgrade between versions of LogLens.

---

## Upgrading to 1.2.0 from 1.1.0

The Queue page now lists each running worker.

### 1. Restart Queue Workers (Required)
```bash
php artisan queue:restart
```
Workers that are still running the old code keep writing the old shared heartbeat and do not appear in the worker list until they restart.

### 2. Optional: Expected Worker Count
Set how many workers should be running (for example your supervisor `numprocs`). The Queue page then warns with "N / M Online" when some are missing:

```dotenv
LOGLENS_QUEUE_EXPECTED_WORKERS=3
```

If you published the config file, add `'expected_workers' => env('LOGLENS_QUEUE_EXPECTED_WORKERS'),` to the `queue` section.

### 3. Custom Storage Implementations
If you implemented `SharedMetricsStorageInterface` yourself, add the registry set methods `touchMember(string $set, string $member, int $maxAgeSeconds): void`, `getMembers(string $set, int $maxAgeSeconds): array` (member => last seen timestamp) and `removeMember(string $set, string $member): void`.

### 4. Republish Views
If you published the views, republish them to get the worker list:

```bash
php artisan vendor:publish --tag=loglens-views --force
```

---

## Upgrading to 1.1.0 from 1.0.0

Queue and scheduler metrics are now persisted to the shared metrics store instead of per-process memory, so the Queue and Scheduler dashboards show real data.

### 1. Restart Queue Workers (Required)
Long-running `queue:work` processes keep the old collector code in memory until they restart. After upgrading, run:

```bash
php artisan queue:restart
```

The scheduler needs no action, because each `schedule:run` invocation loads the new code.

### 2. Use a Shared Cache Store
Metrics are written through Laravel's cache (or Redis when `LOGLENS_METRICS_DRIVER=redis`). The cache store must be shared between your web server, queue workers and scheduler: `file`, `database`, `redis` or `memcached` work, but `array` does not. For multiple servers or busy queues, Redis is recommended.

### 3. Optional: Metrics Retention
Shared counters and history now expire after `loglens.metrics.ttl` seconds (default `604800`, 7 days). If you published the config file, add the key manually or override it via `.env`:

```dotenv
LOGLENS_METRICS_TTL=604800
```

### 4. Custom Storage Implementations
If you implemented `SharedMetricsStorageInterface` yourself:
- add the new `forget(string $key): void` method. It must remove both the value and the history list stored under the key.
- update `recordHeartbeat()` to the new signature `recordHeartbeat(string $collectorName, int $ttlSeconds = 60): void`.

### 5. Optional: Running/Off Status Tuning
The Queue and Scheduler pages now show whether `queue:work` and the scheduler are running. If you published the config file, these keys fall back to their defaults, so add them only if you need different values:

```php
'queue' => [
    'heartbeat_interval_seconds' => 10,  // idle worker writes at most once per interval
    'heartbeat_window_seconds' => 60,    // raise if workers use a large --sleep value
    'busy_window_seconds' => env('LOGLENS_QUEUE_BUSY_WINDOW', 900), // match your longest --timeout
],
'scheduler' => [
    'heartbeat_window_seconds' => 120,
],
```

The cron entry, supervisor and web server must run as the same user (or share a group with writable cache files). Otherwise the web process cannot read the heartbeat, and the status always shows **Off**.

---

## Upgrading to 1.0.0 from Dev Versions

LogLens 1.0.0 represents the first production-ready, stable release.

### 1. Requirements Check
Ensure your environment meets the minimum version constraints:
- **PHP:** `^8.2` (including PHP 8.2 and PHP 8.3)
- **Laravel Framework:** `^11.0` or `^12.0`
- **Composer:** `^2.2`

### 2. Configuration Publishing
If you previously used an early pre-release version of LogLens, republish the updated configuration file to obtain all Phase 3, 4, and 5 config keys (Apache, Queues, Scheduler, System metrics, Redis storage):

```bash
php artisan vendor:publish --tag=loglens-config --force
```

### 3. Review New Configuration Sections
Review the following new configuration blocks in `config/loglens.php`:
- `loglens.system`: Host operating system resource checks (`enabled => false` by default).
- `loglens.queue`: Passive background job instrumentation (`poll_backlog => false` preserves strict zero-query mode).
- `loglens.scheduler`: Scheduled task observation and overlap warnings.
- `loglens.alerts`: Threshold settings and notification cooldown intervals.
- `loglens.metrics`: Storage backend selection (`memory` or `redis`).

### 4. Zero Database Migrations Required
LogLens requires **no database migrations**. There are no monitoring tables to create, migrate, or maintain.

### 5. Asset & View Updates
If you published views with `vendor:publish --tag=loglens-views`, update your published views to incorporate the new dashboard tabs (Server Health, Queues, Scheduler):

```bash
php artisan vendor:publish --tag=loglens-views --force
```
