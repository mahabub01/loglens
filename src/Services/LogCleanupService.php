<?php

namespace MehrabHossain\LogLens\Services;

use DateTimeImmutable;
use MehrabHossain\LogLens\Contracts\LogCleanupInterface;
use MehrabHossain\LogLens\Contracts\LogFileManagerInterface;
use MehrabHossain\LogLens\Exceptions\LogLensException;
use MehrabHossain\LogLens\Exceptions\LogLensSecurityException;
use MehrabHossain\LogLens\ValueObjects\LogFile;

class LogCleanupService implements LogCleanupInterface
{
    public function __construct(
        protected LogFileManagerInterface $fileManager
    ) {
    }

    /**
     * Preview which files would be cleaned up under the retention policy.
     */
    public function previewRetentionCleanup(int $retentionDays = 30): array
    {
        $candidateFiles = $this->getCandidateRetentionFiles($retentionDays);
        $totalBytes = 0;
        $filesReport = [];

        foreach ($candidateFiles as $file) {
            $totalBytes += $file->sizeInBytes;
            $filesReport[] = [
                'identifier' => $file->identifier,
                'filename' => $file->filename,
                'size_bytes' => $file->sizeInBytes,
                'formatted_size' => $file->formattedSize(),
                'modified_at' => $file->modifiedAt->format('Y-m-d H:i:s'),
            ];
        }

        return [
            'files_count' => count($filesReport),
            'total_bytes' => $totalBytes,
            'formatted_size' => $this->formatBytes($totalBytes),
            'files' => $filesReport,
        ];
    }

    /**
     * Execute retention-based log file deletion.
     */
    public function executeRetentionCleanup(int $retentionDays = 30, bool $archiveBeforeDelete = true): array
    {
        $this->ensureDestructiveActionAllowed();

        $candidateFiles = $this->getCandidateRetentionFiles($retentionDays);
        $cleanedFiles = 0;
        $bytesFreed = 0;
        $archivedCount = 0;

        foreach ($candidateFiles as $file) {
            if ($archiveBeforeDelete) {
                if ($this->archiveFile($file)) {
                    $archivedCount++;
                }
            }

            $size = $file->sizeInBytes;
            if (@unlink($file->realPath)) {
                $cleanedFiles++;
                $bytesFreed += $size;
            }
        }

        return [
            'cleaned_files' => $cleanedFiles,
            'bytes_freed' => $bytesFreed,
            'formatted_freed' => $this->formatBytes($bytesFreed),
            'archived_count' => $archivedCount,
        ];
    }

    /**
     * Safely clear (truncate) a specific authorized log file in place.
     */
    public function clearFile(LogFile $file, bool $archiveBeforeClear = false): array
    {
        $this->ensureDestructiveActionAllowed();

        if ($file->isReadOnly) {
            throw new LogLensSecurityException("Cannot clear a read-only log source: {$file->filename}");
        }

        if (! $this->fileManager->isAuthorizedPath($file->realPath)) {
            throw new LogLensSecurityException("Unauthorized file path: {$file->realPath}");
        }

        $bytesCleared = $file->sizeInBytes;
        $wasArchived = false;

        if ($archiveBeforeClear) {
            $wasArchived = $this->archiveFile($file);
        }

        // Safe in-place truncation with an exclusive lock
        $handle = @fopen($file->realPath, 'c+');
        if (! $handle) {
            throw new LogLensException("Failed to open file for truncation: {$file->filename}");
        }

        try {
            if (flock($handle, LOCK_EX)) {
                ftruncate($handle, 0);
                fflush($handle);
                flock($handle, LOCK_UN);
            } else {
                throw new LogLensException("Failed to acquire lock for file truncation: {$file->filename}");
            }
        } finally {
            fclose($handle);
        }

        return [
            'filename' => $file->filename,
            'bytes_cleared' => $bytesCleared,
            'formatted_cleared' => $this->formatBytes($bytesCleared),
            'archived' => $wasArchived,
        ];
    }

    /**
     * Delete an authorized non-primary log file.
     */
    public function deleteFile(LogFile $file): bool
    {
        $this->ensureDestructiveActionAllowed();

        if ($file->isReadOnly) {
            throw new LogLensSecurityException("Cannot delete a read-only log source: {$file->filename}");
        }

        // Avoid deleting the primary singleton laravel.log file; truncate it instead
        if (strtolower($file->filename) === 'laravel.log') {
            throw new LogLensException("The primary laravel.log file cannot be deleted. Clear it instead.");
        }

        if (! $this->fileManager->isAuthorizedPath($file->realPath)) {
            throw new LogLensSecurityException("Unauthorized file path: {$file->realPath}");
        }

        return @unlink($file->realPath);
    }

    /**
     * Locate files eligible for retention cleanup.
     *
     * @return array<int, LogFile>
     */
    protected function getCandidateRetentionFiles(int $retentionDays): array
    {
        $cutoff = (new DateTimeImmutable())->modify("-{$retentionDays} days");
        $allFiles = $this->fileManager->getLogFiles();
        $candidates = [];

        foreach ($allFiles as $file) {
            // Never delete read-only log sources or the primary laravel.log
            if ($file->isReadOnly || strtolower($file->filename) === 'laravel.log') {
                continue;
            }

            // Check if file is older than cutoff threshold
            if ($file->modifiedAt < $cutoff) {
                $candidates[] = $file;
            }
        }

        return $candidates;
    }

    /**
     * Archive a log file before clearing or deleting.
     */
    protected function archiveFile(LogFile $file): bool
    {
        $archiveDir = dirname($file->realPath) . DIRECTORY_SEPARATOR . 'archive';
        if (! is_dir($archiveDir)) {
            @mkdir($archiveDir, 0755, true);
        }

        $timestamp = (new DateTimeImmutable())->format('Ymd_His');
        $archiveFilename = pathinfo($file->filename, PATHINFO_FILENAME) . "_{$timestamp}.log.bak";
        $targetPath = $archiveDir . DIRECTORY_SEPARATOR . $archiveFilename;

        return @copy($file->realPath, $targetPath);
    }

    /**
     * Ensure destructive actions are explicitly permitted in configuration.
     */
    protected function ensureDestructiveActionAllowed(): void
    {
        if (! config('loglens.cleanup.allow_destructive_actions', false)) {
            throw new LogLensSecurityException(
                'Destructive log operations are disabled in config (loglens.cleanup.allow_destructive_actions).'
            );
        }
    }

    /**
     * Helper to format raw bytes.
     */
    protected function formatBytes(int $bytes): string
    {
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
}
