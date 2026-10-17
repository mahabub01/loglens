<?php

namespace MehrabHossain\LogLens\ValueObjects;

use DateTimeImmutable;

class ErrorGroup
{
    public function __construct(
        public readonly string $fingerprint,
        public readonly string $exceptionClass,
        public readonly string $normalizedMessage,
        public readonly string $severity,
        public int $count,
        public DateTimeImmutable $firstSeen,
        public DateTimeImmutable $lastSeen,
        public readonly LogEntry $sampleEntry
    ) {
    }

    /**
     * Record a new occurrence of this error.
     */
    public function recordOccurrence(LogEntry $entry): void
    {
        $this->count++;

        if ($entry->timestamp < $this->firstSeen) {
            $this->firstSeen = $entry->timestamp;
        }

        if ($entry->timestamp > $this->lastSeen) {
            $this->lastSeen = $entry->timestamp;
        }
    }

    /**
     * Determine if this error was first detected within the last X minutes.
     */
    public function isNew(int $withinMinutes = 60): bool
    {
        $threshold = (new DateTimeImmutable())->modify("-{$withinMinutes} minutes");

        return $this->firstSeen >= $threshold;
    }

    /**
     * Convert to array representation.
     */
    public function toArray(): array
    {
        return [
            'fingerprint' => $this->fingerprint,
            'exception_class' => $this->exceptionClass,
            'normalized_message' => $this->normalizedMessage,
            'severity' => $this->severity,
            'count' => $this->count,
            'first_seen' => $this->firstSeen->format('Y-m-d H:i:s'),
            'last_seen' => $this->lastSeen->format('Y-m-d H:i:s'),
            'is_new' => $this->isNew(),
            'sample_entry_id' => $this->sampleEntry->id,
            'sample_message' => $this->sampleEntry->message,
            'sample_stack_trace' => $this->sampleEntry->stackTrace,
        ];
    }
}
