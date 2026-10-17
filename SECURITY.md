# Security Policy

## Supported Versions

Only the latest major and minor release of LogLens receives security updates.

| Version | Supported          |
| ------- | ------------------ |
| 1.0.x   | :white_check_mark: |
| < 1.0   | :x:                |

---

## Reporting a Vulnerability

If you discover a security vulnerability within LogLens, please send an email to **security@example.com** rather than opening a public issue on GitHub. All security vulnerabilities will be promptly addressed.

Please include the following information in your report:
- A description of the vulnerability and its potential impact.
- Detailed reproduction steps or a minimal proof of concept (PoC).
- The version of LogLens, Laravel framework, and PHP being used.
- Any specific configuration settings required to trigger the issue.

We will acknowledge receipt of your report within 48 hours and provide regular updates until a patch is released.

---

## Core Security Architectural Guarantees

LogLens was designed from the ground up under strict defense-in-depth principles:

### 1. Strict Path Traversal Defense
- File access is strictly restricted to configured log directories (`config('loglens.logs.paths')`).
- File identifiers are deterministic 16-character SHA-256 hashes of canonicalized paths.
- Filename parameters containing directory traversal tokens (`..`, `/`, `\`, null bytes `%00`) are immediately rejected with a `LogLensSecurityException`.
- Symbolic links are resolved using `realpath()` and checked against authorized base paths. Any link resolving outside the configured directories is blocked.

### 2. Zero-Extra-Monitoring-Query Guarantee
- LogLens **never** executes SQL queries on the application database solely for monitoring purposes.
- Query listening is purely passive via `DB::listen` and captures metrics in bounded process memory or optional Redis storage.
- No monitoring migrations or database tables are required or created.
- Monitoring overhead on production database clusters is strictly zero.

### 3. Query Binding Privacy & Redaction
- Query parameter bindings (which may contain passwords, API keys, tokens, or personal data) are **never captured or displayed by default**.
- Raw SQL queries are normalized into sanitized fingerprints with parameter values replaced by `?`.
- Sensitive context keys (`password`, `secret`, `token`, `authorization`, `credit_card`) are automatically redacted from log entries and error grouping.

### 4. Cross-Site Scripting (XSS) Prevention
- All log entry messages, stack traces, and exception details are strictly escaped using HTML character entity encoding (`htmlspecialchars`) before rendering in Blade views.
- JSON API responses return data encoded using standard RFC 4627 JSON escaping.

### 5. Server-Side Request Forgery (SSRF) Protection in Apache Monitoring
- The Apache `mod_status` collector only accepts URLs matching strictly configured allowed hosts (`config('loglens.apache.allowed_hosts')`, defaulting to `127.0.0.1`, `localhost`, `::1`).
- Arbitrary URLs provided by end-users or dashboard visitors are rejected.
- Direct outbound HTTP requests to Apache are decoupled from user requests and performed exclusively via a background daemon CLI command (`php artisan loglens:apache-collect`).

### 6. Destructive Operation Protection
- Log truncation and file deletion require three distinct layers of authorization:
  1. `config('loglens.cleanup.allow_destructive_actions')` must be explicitly set to `true`.
  2. The custom `LogLens::destructiveAuth()` callback or `manageLogLens` Gate must evaluate to `true`.
  3. The request must include an explicit confirmation flag (`confirmed: true`) and valid CSRF token.
