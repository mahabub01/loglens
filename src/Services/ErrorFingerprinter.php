<?php

namespace MehrabHossain\LogLens\Services;

use MehrabHossain\LogLens\ValueObjects\LogEntry;

class ErrorFingerprinter
{
    /**
     * Generate a normalized error fingerprint for a LogEntry.
     */
    public function fingerprint(LogEntry $entry): string
    {
        $exception = $entry->exceptionClass ?? 'StandardError';
        $normalizedMessage = $this->normalizeMessage($entry->message);

        return substr(hash('sha256', $exception . '|' . $normalizedMessage), 0, 16);
    }

    /**
     * Normalize dynamic elements out of an error message.
     */
    public function normalizeMessage(string $message): string
    {
        // 1. Normalize UUIDs
        $normalized = preg_replace(
            '/[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}/',
            '[UUID]',
            $message
        );

        // 2. Normalize IP addresses
        $normalized = preg_replace(
            '/\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\b/',
            '[IP]',
            $normalized
        );

        // 3. Normalize Date & Time strings
        $normalized = preg_replace(
            '/\b\d{4}-\d{2}-\d{2}[T\s]\d{2}:\d{2}:\d{2}(?:\.\d+)?\b/',
            '[DATETIME]',
            $normalized
        );

        // 4. Normalize Quoted literals / strings
        $normalized = preg_replace(
            "/('|\")[^'\"\r\n]{3,}('|\")/",
            '$1[STR]$2',
            $normalized
        );

        // 5. Normalize File paths & line numbers (e.g. /var/www/app.php:123)
        $normalized = preg_replace(
            '/:\d+\b/',
            ':[LINE]',
            $normalized
        );

        // 6. Normalize Numeric IDs
        $normalized = preg_replace(
            '/\b(?:id|ID|#)?\d{2,}\b/',
            '[ID]',
            $normalized
        );

        // 7. Collapse repeated whitespace
        $normalized = preg_replace('/\s+/', ' ', trim($normalized));

        return $normalized ?? $message;
    }
}
