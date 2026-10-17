# Contributing to LogLens

Thank you for considering contributing to LogLens!

---

## Core Non-Negotiable Principles

When contributing code or pull requests to LogLens, you must adhere to these architectural requirements:

1. **Strict Zero-Extra-Query Mode:**
   - Never execute SQL queries on the application database solely for monitoring purposes.
   - Do not add database migrations or introduce mandatory monitoring database tables.
   - Any query listening must remain strictly passive via `DB::listen` and store metrics in bounded process memory or optional external storage (e.g., Redis).
2. **Defensive Safe Probes:**
   - Never execute arbitrary shell commands using dashboard input.
   - Never evaluate or accept user-provided collector URLs without strict host whitelisting.
   - Probing host resources must read native files (`/proc`, `/sys/fs/cgroup`) safely. Missing metrics must report `null`, never fabricated as `0`.
3. **No Frontend Build Steps:**
   - The UI must remain pure Blade templates styled with standard Bootstrap 5.3, Font Awesome 6.6, and Chart.js 4.4 loaded via CDN or local assets.
   - Do NOT introduce Tailwind CSS, Vue, React, or Vite build steps.
4. **Security by Default:**
   - Package is disabled by default (`LOGLENS_ENABLED=false`).
   - Destructive actions (truncation, deletion) require three-tier authorization and explicit confirmation.
   - Path traversal must be blocked; file access must be restricted to configured paths.

---

## Development Workflow

1. Clone the repository and install dependencies:
   ```bash
   composer install
   ```
2. Run the test suite:
   ```bash
   vendor/bin/phpunit --testdox
   ```
3. Run performance benchmarks:
   ```bash
   vendor/bin/phpunit tests/Performance/PerformanceBenchmarkTest.php
   ```
4. Verify PHP syntax across all files:
   ```bash
   php -l
   ```
5. Ensure zero database queries are introduced by running:
   ```bash
   vendor/bin/phpunit tests/Security/ZeroExtraQueryTest.php
   vendor/bin/phpunit tests/Security/ZeroDatabaseQueryTest.php
   ```

---

## Coding Standards

- Follow PSR-12 and Laravel coding conventions.
- Keep classes focused and strictly typed with PHP 8.2+ type declarations (`declare(strict_types=1)` or typed properties/returns).
- Preserve existing comments and docstrings.
- Ensure all public contracts and interfaces are documented.
