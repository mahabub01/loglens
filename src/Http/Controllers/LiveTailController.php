<?php

namespace MehrabHossain\LogLens\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use MehrabHossain\LogLens\Contracts\LogFileManagerInterface;
use MehrabHossain\LogLens\Contracts\LogReaderInterface;
use MehrabHossain\LogLens\ValueObjects\LogEntry;

class LiveTailController extends Controller
{
    public function __construct(
        protected LogFileManagerInterface $fileManager,
        protected LogReaderInterface $reader
    ) {
    }

    /**
     * Incrementally tail a log file from a given byte offset.
     */
    public function tail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'string'],
            'offset' => ['nullable', 'integer', 'min:0'],
            'level' => ['nullable', 'string'],
        ]);

        $fileIdentifier = $validated['file'];
        $offset = (int) ($validated['offset'] ?? 0);
        $levelFilter = strtoupper($validated['level'] ?? 'ALL');

        $logFile = $this->fileManager->resolveFile($fileIdentifier);
        $result = $this->reader->readFromOffset($logFile, $offset);

        $entries = $result['entries'];
        if ($levelFilter !== 'ALL') {
            $entries = array_filter($entries, function (LogEntry $entry) use ($levelFilter) {
                return strtoupper($entry->severity) === $levelFilter;
            });
        }

        // Format entries for JSON serialization
        $formattedEntries = array_map(function (LogEntry $entry) {
            return $entry->toArray();
        }, array_values($entries));

        return response()->json([
            'success' => true,
            'file' => $logFile->filename,
            'new_offset' => $result['new_offset'],
            'file_size' => $result['file_size'],
            'was_truncated' => $result['was_truncated'],
            'entries' => $formattedEntries,
        ]);
    }
}
