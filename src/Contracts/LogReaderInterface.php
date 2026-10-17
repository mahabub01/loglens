<?php

namespace MehrabHossain\LogLens\Contracts;

use MehrabHossain\LogLens\ValueObjects\LogFile;

interface LogReaderInterface
{
    /**
     * Read a bounded, paginated segment of entries from the log file (newest first).
     *
     * @param  LogFile  $file
     * @param  int  $page
     * @param  int  $perPage
     * @param  string|null  $severity
     * @param  string|null  $searchQuery
     * @param  string|null  $dateFrom
     * @param  string|null  $dateTo
     * @return array{
     *     entries: array<\MehrabHossain\LogLens\ValueObjects\LogEntry>,
     *     page: int,
     *     per_page: int,
     *     total_scanned: int,
     *     has_more: bool,
     *     file_size: int,
     *     start_offset: int,
     *     end_offset: int
     * }
     */
    public function readPaginated(
        LogFile $file,
        int $page = 1,
        int $perPage = 50,
        ?string $severity = null,
        ?string $searchQuery = null,
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): array;

    /**
     * Incrementally read new bytes appended to a log file starting from an offset.
     *
     * @param  LogFile  $file
     * @param  int  $offset
     * @param  int  $limitBytes
     * @return array{
     *     entries: array<\MehrabHossain\LogLens\ValueObjects\LogEntry>,
     *     new_offset: int,
     *     file_size: int,
     *     was_truncated: bool
     * }
     */
    public function readFromOffset(
        LogFile $file,
        int $offset,
        int $limitBytes = 1048576
    ): array;
}
