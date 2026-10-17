<?php

namespace MehrabHossain\LogLens\Services;

use MehrabHossain\LogLens\Contracts\LogParserInterface;
use MehrabHossain\LogLens\Contracts\LogReaderInterface;
use MehrabHossain\LogLens\Exceptions\LogLensException;
use MehrabHossain\LogLens\ValueObjects\LogEntry;
use MehrabHossain\LogLens\ValueObjects\LogFile;

class BoundedFileReader implements LogReaderInterface
{
    /**
     * Chunk size used when scanning backward from EOF (e.g. 64 KB).
     */
    protected const CHUNK_SIZE = 65536;

    /**
     * Maximum bytes to scan backwards in a single paginated request (default 5 MB).
     */
    protected int $maxScanBytes;

    public function __construct(
        protected LogParserInterface $parser,
        ?int $maxScanBytes = null
    ) {
        $this->maxScanBytes = $maxScanBytes ?? (int) config('loglens.logs.max_read_buffer_bytes', 5242880);
    }

    /**
     * Read a bounded, paginated segment of entries from the log file (newest first).
     */
    public function readPaginated(
        LogFile $file,
        int $page = 1,
        int $perPage = 50,
        ?string $severity = null,
        ?string $searchQuery = null,
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): array {
        if (! file_exists($file->realPath) || ! is_readable($file->realPath)) {
            return [
                'entries' => [],
                'page' => $page,
                'per_page' => $perPage,
                'total_scanned' => 0,
                'has_more' => false,
                'file_size' => 0,
                'start_offset' => 0,
                'end_offset' => 0,
            ];
        }

        $fileSize = (int) filesize($file->realPath);
        if ($fileSize === 0) {
            return [
                'entries' => [],
                'page' => $page,
                'per_page' => $perPage,
                'total_scanned' => 0,
                'has_more' => false,
                'file_size' => 0,
                'start_offset' => 0,
                'end_offset' => 0,
            ];
        }

        $handle = @fopen($file->realPath, 'rb');
        if (! $handle) {
            throw new LogLensException("Unable to open log file for reading: {$file->filename}");
        }

        try {
            $requiredEntriesCount = $page * $perPage;
            // Scan an extra item to reliably determine if there is a next page
            $targetEntriesCount = $requiredEntriesCount + 1;

            $collectedBlocks = [];
            $currentPos = $fileSize;
            $scannedBytes = 0;
            $buffer = '';

            while ($currentPos > 0 && count($collectedBlocks) < $targetEntriesCount && $scannedBytes < $this->maxScanBytes) {
                $readLength = min(self::CHUNK_SIZE, $currentPos);
                $currentPos -= $readLength;
                $scannedBytes += $readLength;

                fseek($handle, $currentPos, SEEK_SET);
                $chunk = fread($handle, $readLength);
                if ($chunk === false) {
                    break;
                }

                $buffer = $chunk . $buffer;

                // Extract all complete log entry blocks from the front of the buffer
                // A log header starts with [YYYY-MM-DD
                $blocks = $this->extractEntryBlocksFromBuffer($buffer, $currentPos === 0);

                if (! empty($blocks)) {
                    // Prepend newly found blocks (since we're scanning backwards from end)
                    $collectedBlocks = array_merge($blocks, $collectedBlocks);
                }
            }

            // Parse collected blocks into LogEntry objects
            $parsedEntries = [];
            foreach ($collectedBlocks as $idx => $block) {
                $entry = $this->parser->parseBlock($block, $idx);
                if ($entry) {
                    if ($this->entryMatchesFilters($entry, $severity, $searchQuery, $dateFrom, $dateTo)) {
                        $parsedEntries[] = $entry;
                    }
                }
            }

            // Sort newest first
            usort($parsedEntries, function (LogEntry $a, LogEntry $b) {
                return $b->timestamp <=> $a->timestamp;
            });

            // Calculate pagination slices
            $offset = ($page - 1) * $perPage;
            $pageEntries = array_slice($parsedEntries, $offset, $perPage);
            $hasMore = count($parsedEntries) > ($offset + $perPage) || ($currentPos > 0 && $scannedBytes < $this->maxScanBytes);

            return [
                'entries' => $pageEntries,
                'page' => $page,
                'per_page' => $perPage,
                'total_scanned' => count($parsedEntries),
                'has_more' => $hasMore,
                'file_size' => $fileSize,
                'start_offset' => $currentPos,
                'end_offset' => $fileSize,
            ];
        } finally {
            fclose($handle);
        }
    }

    /**
     * Incrementally read new bytes appended to a log file starting from a given offset (Live Tail).
     */
    public function readFromOffset(
        LogFile $file,
        int $offset,
        int $limitBytes = 1048576
    ): array {
        if (! file_exists($file->realPath) || ! is_readable($file->realPath)) {
            return [
                'entries' => [],
                'new_offset' => $offset,
                'file_size' => 0,
                'was_truncated' => false,
            ];
        }

        clearstatcache(true, $file->realPath);
        $fileSize = (int) filesize($file->realPath);
        $wasTruncated = false;

        // If file size is smaller than the requested offset, file was rotated or truncated
        if ($fileSize < $offset) {
            $offset = 0;
            $wasTruncated = true;
        }

        // If no new content
        if ($fileSize <= $offset) {
            return [
                'entries' => [],
                'new_offset' => $fileSize,
                'file_size' => $fileSize,
                'was_truncated' => $wasTruncated,
            ];
        }

        $handle = @fopen($file->realPath, 'rb');
        if (! $handle) {
            throw new LogLensException("Unable to open log file for reading: {$file->filename}");
        }

        try {
            fseek($handle, $offset, SEEK_SET);

            $bytesToRead = min($fileSize - $offset, $limitBytes);
            $content = fread($handle, $bytesToRead);
            $newOffset = ftell($handle);

            $entries = [];
            if ($content !== false && strlen($content) > 0) {
                $entries = $this->parser->parseContent($content);
            }

            return [
                'entries' => $entries,
                'new_offset' => (int) $newOffset,
                'file_size' => $fileSize,
                'was_truncated' => $wasTruncated,
            ];
        } finally {
            fclose($handle);
        }
    }

    /**
     * Extract discrete log entry blocks from a text buffer.
     * Retains any incomplete leading block in the buffer unless $isStartOfFile is true.
     */
    protected function extractEntryBlocksFromBuffer(string &$buffer, bool $isStartOfFile): array
    {
        $blocks = [];
        $pattern = '/(?=(?:^|\r\n|\n)\[\d{4}-\d{2}-\d{2}[T\s]\d{2}:\d{2}:\d{2})/';
        $parts = preg_split($pattern, $buffer, -1, PREG_SPLIT_NO_EMPTY);

        if (! $parts) {
            return [];
        }

        if ($isStartOfFile) {
            // Whole buffer is available from beginning of file
            $buffer = '';

            return array_values(array_filter(array_map('trim', $parts)));
        }

        // The first part may be an incomplete fragment from the previous chunk
        $buffer = array_shift($parts) ?? '';

        return array_values(array_filter(array_map('trim', $parts)));
    }

    /**
     * Test if a parsed log entry matches all applied filters.
     */
    protected function entryMatchesFilters(
        LogEntry $entry,
        ?string $severity,
        ?string $searchQuery,
        ?string $dateFrom,
        ?string $dateTo
    ): bool {
        // Severity filter
        if ($severity && $severity !== 'ALL') {
            if (strtoupper($entry->severity) !== strtoupper($severity)) {
                return false;
            }
        }

        // Keyword search query
        if ($searchQuery && ! empty(trim($searchQuery))) {
            $query = mb_strtolower(trim($searchQuery));
            $foundInMessage = str_contains(mb_strtolower($entry->message), $query);
            $foundInException = $entry->exceptionClass && str_contains(mb_strtolower($entry->exceptionClass), $query);
            $foundInTrace = $entry->stackTrace && str_contains(mb_strtolower($entry->stackTrace), $query);

            if (! $foundInMessage && ! $foundInException && ! $foundInTrace) {
                return false;
            }
        }

        // Date range filter
        if ($dateFrom) {
            try {
                $from = new \DateTimeImmutable($dateFrom);
                if ($entry->timestamp < $from) {
                    return false;
                }
            } catch (\Exception) {
            }
        }

        if ($dateTo) {
            try {
                $to = new \DateTimeImmutable($dateTo);
                if ($entry->timestamp > $to) {
                    return false;
                }
            } catch (\Exception) {
            }
        }

        return true;
    }
}
