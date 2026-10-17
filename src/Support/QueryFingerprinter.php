<?php

namespace MehrabHossain\LogLens\Support;

class QueryFingerprinter
{
    /**
     * Compute a normalized query fingerprint.
     */
    public function fingerprint(string $sql): string
    {
        $normalized = $this->normalize($sql);

        return substr(hash('sha256', $normalized), 0, 16);
    }

    /**
     * Normalize SQL statements by stripping literals, quoted strings, and numeric IDs.
     */
    public function normalize(string $sql): string
    {
        // 1. Remove single-quoted string literals
        $normalized = preg_replace("/'[^']*'/", '?', $sql);

        // 2. Remove double-quoted string literals
        $normalized = preg_replace('/"[^"]*"/', '?', $normalized);

        // 3. Normalize UUID strings
        $normalized = preg_replace(
            '/[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}/',
            '?',
            $normalized
        );

        // 4. Normalize IN clause lists: e.g. IN (1, 2, 3) or IN (?, ?, ?) -> IN (?)
        $normalized = preg_replace('/\bIN\s*\([^\)]+\)/i', 'IN (?)', $normalized);

        // 5. Normalize integer and float numbers
        $normalized = preg_replace('/\b\d+(?:\.\d+)?\b/', '?', $normalized);

        // 6. Normalize boolean literals
        $normalized = preg_replace('/\b(true|false)\b/i', '?', $normalized);

        // 7. Collapse spaces and line breaks
        $normalized = preg_replace('/\s+/', ' ', trim($normalized));

        return $normalized ?? $sql;
    }

    /**
     * Redact sensitive keyword patterns from SQL if raw SQL logging is configured.
     */
    public function redact(string $sql): string
    {
        $sensitiveKeys = config('loglens.security.sensitive_keys', [
            'password', 'secret', 'token', 'key',
        ]);

        foreach ($sensitiveKeys as $key) {
            $pattern = '/([\'"`]?' . preg_quote($key, '/') . '[\'"`]?\s*=\s*)([\'"][^\'"]*[\'"]|\S+)/i';
            $sql = preg_replace($pattern, '$1[REDACTED]', $sql);
        }

        return $sql;
    }
}
