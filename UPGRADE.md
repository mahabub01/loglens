# Upgrade Guide

This guide describes how to upgrade between versions of LogLens.

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
