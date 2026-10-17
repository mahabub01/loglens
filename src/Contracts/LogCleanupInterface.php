<?php

namespace MehrabHossain\LogLens\Contracts;

use MehrabHossain\LogLens\ValueObjects\LogFile;

interface LogCleanupInterface
{
    /**
     * Preview which files would be cleaned up under the retention policy.
     *
     * @param  int  $retentionDays
     * @return array{
     *     files_count: int,
     *     total_bytes: int,
     *     formatted_size: string,
     *     files: array<int, array{filename: string, size_bytes: int, formatted_size: string, modified_at: string}>
     * }
     */
    public function previewRetentionCleanup(int $retentionDays = 30): array;

    /**
     * Execute retention-based log file deletion.
     *
     * @param  int  $retentionDays
     * @param  bool  $archiveBeforeDelete
     * @return array{
     *     cleaned_files: int,
     *     bytes_freed: int,
     *     formatted_freed: string,
     *     archived_count: int
     * }
     */
    public function executeRetentionCleanup(int $retentionDays = 30, bool $archiveBeforeDelete = true): array;

    /**
     * Safely clear (truncate) a specific authorized log file without removing
     * the open file descriptor from concurrent worker processes.
     */
    public function clearFile(LogFile $file, bool $archiveBeforeClear = false): array;

    /**
     * Delete an authorized non-primary log file.
     */
    public function deleteFile(LogFile $file): bool;
}
