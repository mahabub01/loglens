<p align="center">
  <br>
  <h1 align="center">🔍 LogLens</h1>
  <p align="center">
    <strong>Developer-focused application log analyzer and real-time monitoring dashboard for Laravel.</strong><br>
    <em>Lightweight &bull; Zero Migrations &bull; Zero Extra Database Queries &bull; Production Hardened</em>
  </p>
  <p align="center">
    <a href="https://packagist.org/packages/mehrabhossain/loglens"><img src="https://img.shields.io/packagist/v/mehrabhossain/loglens.svg?style=for-the-badge&color=blue" alt="Latest Version"></a>
    <a href="https://packagist.org/packages/mehrabhossain/loglens"><img src="https://img.shields.io/packagist/dt/mehrabhossain/loglens.svg?style=for-the-badge&color=purple" alt="Total Downloads"></a>
    <img src="https://img.shields.io/badge/PHP-%5E8.1%20%7C%7C%20%5E8.2%20%7C%7C%20%5E8.3%20%7C%7C%20%5E8.4-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP Versions">
    <img src="https://img.shields.io/badge/Laravel-10%20%7C%7C%2011%20%7C%7C%2012%20%7C%7C%2013-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel Versions">
    <img src="https://img.shields.io/badge/Extra_DB_Queries-0_(Guaranteed)-00b894?style=for-the-badge" alt="Zero Extra Queries">
    <img src="https://img.shields.io/badge/Database_Migrations-0_(No_Tables)-0984e3?style=for-the-badge" alt="Zero Migrations">
    <img src="https://img.shields.io/badge/Tests-202_Passed_(100%25)-2ecc71?style=for-the-badge" alt="Tests Passed">
    <a href="LICENSE"><img src="https://img.shields.io/badge/License-MIT-black?style=for-the-badge" alt="License"></a>
  </p>
</p>

---

## 🌟 What is LogLens?

**LogLens** is a modern, high-performance developer dashboard and diagnostic package built specifically for **Laravel (versions 10, 11, 12, and 13)**. It brings together graphical log file management, HTTP latency metrics, database query observation, Apache server health, host OS and Docker container telemetry, background queue monitoring, and console scheduler observability into a single, cohesive interface.

Most monitoring tools pollute your application database with tens of thousands of telemetry rows or execute heavy diagnostic queries that slow down production clusters. **LogLens operates under a strict Zero-Extra-Query & Zero-Migration architectural guarantee.**

---

## 🛡️ Core Non-Negotiable Guarantees

> [!IMPORTANT]
> 1. **Strict Zero-Extra-Query Mode:** LogLens **never** executes SQL queries on your application database solely for monitoring. Query profiling is 100% passive via Laravel's native `DB::listen`.
> 2. **Zero Database Migrations:** There are **zero** database tables to create, migrate, or maintain. All core operations run in bounded memory or an optional external adapter (e.g. Redis).
> 3. **Zero Frontend Build Dependencies:** Powered by pure Blade + Bootstrap 5 + Font Awesome 6 + Chart.js. **No Tailwind CSS, Node, Vite, or npm build steps required.**
> 4. **Safe Native Probes Only:** Never executes arbitrary shell commands from dashboard input. Missing OS metrics report `null` and are **never fabricated as zero**.
> 5. **Safe by Default:** Disabled out of the box (`LOGLENS_ENABLED=false`) with multi-tier authorization guards on all read and destructive endpoints.

---

## 🚀 Key Features

| Feature | Description |
|---|---|
| 📜 **Log File Analyzer** | Streaming, bounded reader capable of parsing massive (>100MB) log files without memory spikes. Monolog 3 support, severity filtering, and full stack trace formatting. |
| ⚡ **Live Tail & Error Grouping** | Real-time incremental log streaming via polling and automatic SHA-256 error fingerprinting for repeated exceptions. |
| 🗄️ **Zero-Query Database Observer** | Passive SQL query profiling, slow query detection (`>500ms`), and automatic N+1 heuristic query warnings with sanitized parameter bindings. |
| 📈 **HTTP Performance & Latency** | Request timing, throughput, status code distribution (2xx, 3xx, 4xx, 5xx), and statistical latency percentiles (**Median, p95, p99**). |
| 🌐 **Apache mod_status Live Monitor** | 1-second interval shared collector daemon tracking Requests/sec, Bytes/sec, and active/idle worker scoreboard with SSRF protection. |
| 🖥️ **Host & Container Resource Telemetry** | Probes CPU utilization, load averages, memory, swap, filesystem allocation, and true container cgroups (v1 and v2) memory limits. |
| ⏱️ **Queue & Scheduler Monitoring** | Tracks processed/failed jobs, execution timing, long-running jobs, scheduler dispatches, and `withoutOverlapping` mutex skip warnings. |
| 🔔 **Alert Engine with Cooldown** | Evaluates threshold alerts (High CPU, Low Disk, High Memory, Elevated 5xx Rate, Queue Failures) with cooldown deduplication against alert storms. |
| 🗄️ **Optional Shared Storage** | Standalone memory mode by default; optional Redis adapter for atomic counters, sliding history, and multi-worker clusters. |

---

## 📋 Compatibility & Requirements

| Component | Supported Versions |
|---|---|
| **PHP** | `^8.1`, `^8.2`, `^8.3`, `^8.4` |
| **Laravel Framework** | `^10.0`, `^11.0`, `^12.0`, `^13.0` |
| **Frontend Dependencies** | None! (Blade, Bootstrap 5, Font Awesome, and Chart.js bundled / CDN) |
| **Database Migrations** | **None** (zero monitoring tables created) |

---

## 📦 Installation

Install LogLens via Composer:

```bash
composer require mehrabhossain/loglens
```

LogLens supports Laravel package auto-discovery. The `LogLensServiceProvider` and `LogLens` facade will be registered automatically.

### Publish Configuration

Publish the configuration file to `config/loglens.php`:

```bash
php artisan vendor:publish --tag=loglens-config
```

*(Optional)* Publish Blade views to customize the UI:

```bash
php artisan vendor:publish --tag=loglens-views
```

---

## ⚙️ Quick Start Configuration

By default, LogLens is **disabled** to ensure zero unintended overhead in production:

```env
LOGLENS_ENABLED=false
```

To enable the dashboard in your environment, add the following to your `.env` file:

```env
# Enable LogLens Master Switch
LOGLENS_ENABLED=true
LOGLENS_PATH=loglens

# Enable Monitoring Modules
LOGLENS_DB_MONITORING=true
LOGLENS_HTTP_MONITORING=true
LOGLENS_SYSTEM_MONITORING=true
LOGLENS_QUEUE_MONITORING=true
LOGLENS_SCHEDULER_MONITORING=true
LOGLENS_ALERTS_ENABLED=true

# Security & Destructive Actions
LOGLENS_ALLOW_DESTRUCTIVE=false
```

Start your Laravel development server:

```bash
php artisan serve
```

Navigate to: **`http://localhost:8000/loglens`**

---

## 🔒 Access Control & Authorization (Locking Down the Dashboard)

LogLens provides multiple defense-in-depth authorization layers to ensure that unauthorized users or the public cannot access your monitoring dashboard.

### 1. Custom Callback Authorization (Recommended)

In your `app/Providers/AppServiceProvider.php`, use the `LogLens::auth()` callback to restrict access strictly to authenticated team members:

```php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use MehrabHossain\LogLens\Facades\LogLens;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Authorize dashboard view access (Read-Only)
        LogLens::auth(function ($request) {
            $user = $request->user();

            return $user && in_array($user->email, [
                'lead-developer@example.com',
                'admin@example.com',
                'devops@example.com',
            ]);
        });

        // Authorize destructive actions (clearing/deleting log files)
        LogLens::destructiveAuth(function ($request) {
            return $request->user()?->email === 'lead-architect@example.com';
        });
    }
}
```

> [!NOTE]
> Any unauthenticated or unauthorized visitor attempting to access `/loglens` will immediately receive a **`403 Forbidden`** response.

### 2. Standard Laravel Gate Integration

Alternatively, register standard Laravel Gates in your `AuthServiceProvider` (or `AppServiceProvider` in Laravel 11/12):

```php
use Illuminate\Support\Facades\Gate;

Gate::define('viewLogLens', function ($user) {
    return $user->hasRole('admin') || $user->hasRole('super-admin');
});

Gate::define('manageLogLens', function ($user) {
    return $user->hasRole('super-admin');
});
```

### 3. Automatically Redirect Unauthenticated Visitors to `/login`

If you want visitors without an active session to be automatically redirected to your application's login page instead of seeing a 403 page, simply add Laravel's native `'auth'` middleware to `config/loglens.php`:

```php
// config/loglens.php

'middleware' => [
    'web',
    'auth', // <-- Automatically redirects unauthenticated guests to /login
],
```

### 4. Restricting by IP Whitelist (Office / VPN Only)

To restrict dashboard access exclusively to specific static IP addresses or internal corporate VPNs:

```php
LogLens::auth(function ($request) {
    $allowedIps = [
        '127.0.0.1',
        '10.0.1.50',
        '203.0.113.10', // Corporate static IP
    ];

    return in_array($request->ip(), $allowedIps, true);
});
```

### 5. Disable Local Environment Auto-Access

By default, LogLens allows direct access in local environments (`APP_ENV=local`) to make development convenient. To enforce strict authentication even on your local machine, set:

```php
// config/loglens.php

'auth' => [
    'gate' => 'viewLogLens',
    'destructive_gate' => 'manageLogLens',
    'allow_local_by_default' => false, // <-- Enforces authorization on local environment
],
```

### 6. Obscure the Dashboard URL

You can change the URL prefix from `/loglens` to any secret path in your `.env`:

```env
LOGLENS_PATH=internal-diagnostic-portal-9821
```

---

## 🗺️ Dashboard Directory & Routes

All routes are automatically prefixed under your configured `LOGLENS_PATH` (`/loglens` by default) and protected by the `AuthorizeLogLens` middleware:

| Route URI | Purpose | Description |
|---|---|---|
| `/loglens` | **Overview Dashboard** | High-level summary of logs, HTTP, DB, server health, and active alerts |
| `/loglens/logs` | **Log Viewer** | Paginated streaming log browser, severity badges, and keyword search |
| `/loglens/errors` | **Error Analysis** | Deduplicated error grouping with occurrence counts and stack traces |
| `/loglens/http` | **HTTP Performance** | Request latency, status distributions, percentiles (p50, p95, p99), slow routes |
| `/loglens/database` | **Database Profiler** | Normalized query fingerprints, slow query alerts, connection distribution |
| `/loglens/apache` | **Apache Status** | Live requests/second, bytes/second, worker scoreboard (1s polling) |
| `/loglens/server-health` | **Host Health** | CPU, Load average, RAM, swap, disk mount allocation, Docker cgroups |
| `/loglens/queue` | **Queue Telemetry** | Processed jobs, failed jobs, execution durations, long-running jobs |
| `/loglens/scheduler` | **Scheduler Monitor** | Scheduled task executions, clean exits, failures, and mutex overlap warnings |

---

## 🌐 Apache mod_status Live Monitoring

LogLens connects to Apache's native `mod_status` module to observe web server performance without exposing the status endpoint to the public.

### Architecture

```
[ Apache Server ]  --->  [ artisan loglens:apache-collect ]  --->  [ Shared Cache (Redis/File) ]
                                (Background Daemon)                           │
                                                                              ▼
[ Dashboard Visitors ]  <-------------------------------------------  [ Read Instant Snapshot ]
                                  (0 Direct Apache Hits)
```

### Setup Instructions

1. Enable `mod_status` in Apache:
   ```apache
   <Location "/server-status">
       SetHandler server-status
       Require local
   </Location>
   ExtendedStatus On
   ```
2. Enable Apache monitoring in `.env`:
   ```env
   LOGLENS_APACHE_MONITORING=true
   LOGLENS_APACHE_STATUS_URL=http://127.0.0.1/server-status?auto
   ```
3. Run the background collector daemon:
   ```bash
   php artisan loglens:apache-collect
   ```

---

## ⚡ Performance Benchmarks

Measured on **PHP 8.3** using [`tests/Performance/PerformanceBenchmarkTest.php`](tests/Performance/PerformanceBenchmarkTest.php):

| Benchmark Scenario | Measured Result | Performance Assessment |
|---|---|---|
| **Query Event Handling** | **10.44 µs** per event (**95,799 queries/sec**) | Negligible overhead on `DB::listen` |
| **HTTP Request Collection** | **64.06 µs** per request (**15,615 req/sec**) | Sub-millisecond middleware footprint |
| **Statistical Percentile Calculation** | **0.39 ms** (Median, p95, p99 on 2,000 samples) | Fast in-memory statistical ranking |
| **Rapid Log Streaming** | **16.66 ms** for 1,000 lines (**60,021 lines/sec**) | Non-blocking live tail polling |
| **Large Log Parsing** | **10.63 ms** for 10,000 lines (1.1 MB) | Zero memory growth (bounded reader) |
| **Worker RAM Stability** | **0.00 MB growth** over 1,000 job cycles | Zero memory leak in long-running processes |
| **Dashboard Response Latency** | **2.2 ms – 5.5 ms** across all views | Instantaneous sub-10ms page rendering |

---

## 🧪 Testing & Quality Assurance

LogLens includes an exhaustive automated test suite covering unit, feature, security, and performance scenarios:

```bash
vendor/bin/phpunit --testdox
```

```
Testsuites: Unit, Feature, Security, Performance
Tests:      202 passed (100%)
Assertions: 872 passed
Duration:   ~4.0 seconds
```

### Strict Zero-Extra-Query Verification Test

```bash
vendor/bin/phpunit tests/Security/ZeroDatabaseQueryTest.php
vendor/bin/phpunit tests/Security/ZeroExtraQueryTest.php
```

---

## 🚀 Production Deployment Checklist

Before deploying LogLens to a live production cluster:

1. [ ] **Master Switch Configured:** Ensure `LOGLENS_ENABLED=true` in production `.env`.
2. [ ] **Authorization Registered:** Register `LogLens::auth()` in `AppServiceProvider` or define the `viewLogLens` Gate.
3. [ ] **Destructive Actions Restricted:** Keep `LOGLENS_ALLOW_DESTRUCTIVE=false` unless log truncation is explicitly authorized via `LogLens::destructiveAuth()`.
4. [ ] **Allowed Apache Hosts:** If using Apache monitoring, verify `LOGLENS_APACHE_STATUS_URL` points to `http://127.0.0.1/server-status?auto` and allowed hosts are restricted.
5. [ ] **Queue Backlog Strict Mode:** Leave `LOGLENS_QUEUE_POLL_BACKLOG=false` to ensure zero database queries on queue monitoring dashboards.
6. [ ] **Redis Prefix Isolation:** If multiple applications share a Redis instance, configure distinct `LOGLENS_REDIS_PREFIX=myapp:loglens:metrics`.
7. [ ] **Alert Thresholds:** Adjust CPU, Memory, Disk, and HTTP error rate alert thresholds in `config/loglens.php` to match your infrastructure capacity.

---

## 🤝 Contributing

Contributions, bug reports, and pull requests are welcome! Please review [CONTRIBUTING.md](CONTRIBUTING.md) for details on code style, testing, and submission guidelines.

---

## 🔒 Security Policy

If you discover a security vulnerability within LogLens, please review our [Security Policy](SECURITY.md).

---

## 📄 License

LogLens is open-sourced software licensed under the **[MIT license](LICENSE)**.

<p align="center">
  Crafted with care by <strong>Mehrab Hossain</strong> &bull; Made for Laravel Developers
</p>
