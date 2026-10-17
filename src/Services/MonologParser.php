<?php

namespace MehrabHossain\LogLens\Services;

use DateTimeImmutable;
use MehrabHossain\LogLens\Contracts\LogParserInterface;
use MehrabHossain\LogLens\ValueObjects\LogEntry;

class MonologParser implements LogParserInterface
{
    /**
     * Regex pattern matching the standard Laravel Monolog header:
     * [2026-10-17 12:00:00] local.ERROR: Message here {"context":"..."} {"extra":"..."}
     */
    protected const LOG_HEADER_PATTERN = '/^\[(?P<datetime>\d{4}-\d{2}-\d{2}[T\s]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:[+-]\d{2}:?\d{2}|Z)?)\]\s+(?:(?P<channel>[\w\.\-]+)\.)?(?P<severity>[a-zA-Z]+):\s*(?P<message>.*)$/s';

    /**
     * Parse a raw chunk of log lines into structured LogEntry objects.
     * Handles splitting by entry header and folding multiline stack traces.
     */
    public function parseContent(string $rawContent): array
    {
        if (empty(trim($rawContent))) {
            return [];
        }

        $entries = [];
        $blocks = $this->splitIntoEntryBlocks($rawContent);

        foreach ($blocks as $index => $block) {
            $entry = $this->parseBlock($block, $index);
            if ($entry) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * Split continuous raw log content into discrete log entry blocks based on timestamp headers.
     */
    protected function splitIntoEntryBlocks(string $rawContent): array
    {
        $lines = preg_split("/\r\n|\n|\r/", $rawContent);
        if (! $lines) {
            return [];
        }

        $blocks = [];
        $currentBlock = [];

        foreach ($lines as $line) {
            // Check if line begins with a log timestamp header: [YYYY-MM-DD
            if (preg_match('/^\[\d{4}-\d{2}-\d{2}[T\s]\d{2}:\d{2}:\d{2}/', $line)) {
                if (! empty($currentBlock)) {
                    $blocks[] = implode("\n", $currentBlock);
                    $currentBlock = [];
                }
            }
            $currentBlock[] = $line;
        }

        if (! empty($currentBlock)) {
            $blocks[] = implode("\n", $currentBlock);
        }

        return $blocks;
    }

    /**
     * Parse a single multiline block into a LogEntry value object.
     */
    public function parseBlock(string $block, int $index = 0): ?LogEntry
    {
        $trimmed = trim($block);
        if (empty($trimmed)) {
            return null;
        }

        $lines = explode("\n", $block);
        $firstLine = $lines[0] ?? '';

        if (preg_match(self::LOG_HEADER_PATTERN, $firstLine, $matches)) {
            $datetimeStr = $matches['datetime'];
            $channel = ! empty($matches['channel']) ? $matches['channel'] : 'local';
            $severity = strtoupper($matches['severity']);
            $firstLineMessage = $matches['message'] ?? '';

            $remainingLines = array_slice($lines, 1);
            $fullMessageWithTrace = ! empty($remainingLines)
                ? $firstLineMessage . "\n" . implode("\n", $remainingLines)
                : $firstLineMessage;

            // Separate message from stack trace and parse context
            [$cleanMessage, $stackTrace] = $this->extractStackTrace($firstLineMessage, $remainingLines);
            [$cleanMessage, $context, $extra] = $this->extractContextAndExtra($cleanMessage);

            // Redact sensitive data if enabled
            if (config('loglens.security.redact_sensitive_data', true)) {
                $cleanMessage = $this->redactSensitiveString($cleanMessage);
                $context = $this->redactSensitiveArray($context);
                $extra = $this->redactSensitiveArray($extra);
            }

            $exceptionClass = $this->detectExceptionClass($cleanMessage, $stackTrace);

            try {
                $timestamp = new DateTimeImmutable($datetimeStr);
            } catch (\Exception $e) {
                $timestamp = new DateTimeImmutable();
            }

            $id = substr(hash('sha256', $datetimeStr . '|' . $firstLine . '|' . $index), 0, 16);

            return new LogEntry(
                id: $id,
                timestamp: $timestamp,
                channel: $channel,
                severity: $severity,
                message: $cleanMessage,
                context: $context,
                extra: $extra,
                exceptionClass: $exceptionClass,
                stackTrace: $stackTrace,
                raw: $block
            );
        }

        // Fallback for unparseable or malformed entry
        $id = substr(hash('sha256', $firstLine . '|' . $index), 0, 16);
        $cleanContent = config('loglens.security.redact_sensitive_data', true)
            ? $this->redactSensitiveString($block)
            : $block;

        return new LogEntry(
            id: $id,
            timestamp: new DateTimeImmutable(),
            channel: 'raw',
            severity: 'INFO',
            message: $cleanContent,
            context: [],
            extra: [],
            exceptionClass: null,
            stackTrace: null,
            raw: $block
        );
    }

    /**
     * Separate the exception message from trailing multiline stack trace.
     */
    protected function extractStackTrace(string $firstLineMessage, array $remainingLines): array
    {
        if (empty($remainingLines)) {
            return [$firstLineMessage, null];
        }

        $messageLines = [$firstLineMessage];
        $traceLines = [];
        $inTrace = false;

        foreach ($remainingLines as $line) {
            $trimmedLine = trim($line);

            if ($inTrace) {
                $traceLines[] = $line;
                continue;
            }

            // Detect typical stack trace indicators
            if (
                preg_match('/^#\d+\s+/', $trimmedLine) ||
                str_starts_with($trimmedLine, '[stacktrace]') ||
                str_starts_with($trimmedLine, 'Stack trace:') ||
                str_starts_with($trimmedLine, '{"exception":')
            ) {
                $inTrace = true;
                $traceLines[] = $line;
            } else {
                $messageLines[] = $line;
            }
        }

        $message = trim(implode("\n", $messageLines));
        $stackTrace = ! empty($traceLines) ? trim(implode("\n", $traceLines)) : null;

        return [$message, $stackTrace];
    }

    /**
     * Extract JSON context and extra objects trailing the message.
     */
    protected function extractContextAndExtra(string $message): array
    {
        $context = [];
        $extra = [];

        // Check if message ends with JSON objects: e.g. {"context":"..."} {"extra":"..."}
        if (preg_match('/^(.*?)\s*(\{[^\r\n]*\})\s*(\{[^\r\n]*\})?\s*$/s', $message, $m)) {
            $potentialContext = json_decode($m[2], true);
            if (is_array($potentialContext)) {
                $context = $potentialContext;
                $message = trim($m[1]);
            }

            if (! empty($m[3])) {
                $potentialExtra = json_decode($m[3], true);
                if (is_array($potentialExtra)) {
                    $extra = $potentialExtra;
                }
            }
        }

        return [$message, $context, $extra];
    }

    /**
     * Detect exception class name from message or stack trace.
     */
    protected function detectExceptionClass(string $message, ?string $stackTrace): ?string
    {
        // E.g.: "Illuminate\Database\QueryException: ..." or "Class 'App\Models\User' not found"
        if (preg_match('/^([a-zA-Z0-9_\\\\]+(?:Exception|Error)):/m', $message, $m)) {
            return $m[1];
        }

        if (preg_match('/([a-zA-Z0-9_\\\\]+(?:Exception|Error))\b/', $message, $m)) {
            return $m[1];
        }

        if ($stackTrace && preg_match('/([a-zA-Z0-9_\\\\]+(?:Exception|Error))\b/', $stackTrace, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * Redact sensitive keys from a string.
     */
    protected function redactSensitiveString(string $content): string
    {
        $sensitiveKeys = config('loglens.security.sensitive_keys', [
            'password', 'secret', 'token', 'authorization', 'bearer', 'key',
        ]);

        foreach ($sensitiveKeys as $key) {
            $pattern = '/([\'"]?' . preg_quote($key, '/') . '[\'"]?\s*[:=]\s*[\'"]?)([^\s\'",;&]+)([\'"]?)/i';
            $content = preg_replace($pattern, '$1[REDACTED]$3', $content);
        }

        return $content;
    }

    /**
     * Recursively redact sensitive keys from an array.
     */
    protected function redactSensitiveArray(array $data): array
    {
        $sensitiveKeys = array_map('strtolower', config('loglens.security.sensitive_keys', [
            'password', 'secret', 'token', 'authorization', 'bearer', 'key',
        ]));

        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), $sensitiveKeys, true)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = $this->redactSensitiveArray($value);
            }
        }

        return $data;
    }
}
