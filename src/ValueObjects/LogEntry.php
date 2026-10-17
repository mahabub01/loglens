<?php

namespace MehrabHossain\LogLens\ValueObjects;

use DateTimeImmutable;

class LogEntry
{
    public function __construct(
        public readonly string $id,
        public readonly DateTimeImmutable $timestamp,
        public readonly string $channel,
        public readonly string $severity,
        public readonly string $message,
        public readonly array $context = [],
        public readonly array $extra = [],
        public readonly ?string $exceptionClass = null,
        public readonly ?string $stackTrace = null,
        public readonly string $raw = '',
        public ?string $fingerprint = null
    ) {
    }

    /**
     * Check if this entry represents an error or higher severity.
     */
    public function isError(): bool
    {
        return in_array(strtoupper($this->severity), [
            'EMERGENCY',
            'ALERT',
            'CRITICAL',
            'ERROR',
        ], true);
    }

    /**
     * Get the Bootstrap badge color class corresponding to this severity.
     */
    public function badgeClass(): string
    {
        return match (strtoupper($this->severity)) {
            'EMERGENCY', 'ALERT', 'CRITICAL' => 'danger',
            'ERROR' => 'danger',
            'WARNING' => 'warning',
            'NOTICE' => 'info',
            'INFO' => 'primary',
            'DEBUG' => 'secondary',
            default => 'secondary',
        };
    }

    /**
     * Convert to an array for JSON responses.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s'),
            'channel' => $this->channel,
            'severity' => strtoupper($this->severity),
            'badge_class' => $this->badgeClass(),
            'message' => $this->message,
            'context' => $this->context,
            'extra' => $this->extra,
            'exception_class' => $this->exceptionClass,
            'stack_trace' => $this->stackTrace,
            'has_stack_trace' => ! empty($this->stackTrace),
            'fingerprint' => $this->fingerprint,
        ];
    }
}
