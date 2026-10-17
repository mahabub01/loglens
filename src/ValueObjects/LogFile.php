<?php

namespace MehrabHossain\LogLens\ValueObjects;

use DateTimeImmutable;

class LogFile
{
    public function __construct(
        public readonly string $identifier,
        public readonly string $filename,
        public readonly string $relativePath,
        public readonly string $realPath,
        public readonly int $sizeInBytes,
        public readonly DateTimeImmutable $modifiedAt,
        public readonly bool $isWritable,
        public readonly bool $isReadOnly = false
    ) {
    }

    /**
     * Get a human-readable file size (e.g. "12.4 MB").
     */
    public function formattedSize(): string
    {
        $bytes = $this->sizeInBytes;

        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' B';
    }

    /**
     * Convert to an array representation.
     */
    public function toArray(): array
    {
        return [
            'identifier' => $this->identifier,
            'filename' => $this->filename,
            'relative_path' => $this->relativePath,
            'size_bytes' => $this->sizeInBytes,
            'formatted_size' => $this->formattedSize(),
            'modified_at' => $this->modifiedAt->format('Y-m-d H:i:s'),
            'is_writable' => $this->isWritable,
            'is_read_only' => $this->isReadOnly,
        ];
    }
}
