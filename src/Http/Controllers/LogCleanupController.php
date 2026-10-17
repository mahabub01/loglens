<?php

namespace MehrabHossain\LogLens\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use MehrabHossain\LogLens\Contracts\LogCleanupInterface;
use MehrabHossain\LogLens\Contracts\LogFileManagerInterface;

class LogCleanupController extends Controller
{
    public function __construct(
        protected LogFileManagerInterface $fileManager,
        protected LogCleanupInterface $cleanupService
    ) {
    }

    /**
     * Preview candidate log files for retention cleanup.
     */
    public function preview(Request $request): JsonResponse
    {
        $retentionDays = max(1, (int) $request->input('retention_days', 30));
        $preview = $this->cleanupService->previewRetentionCleanup($retentionDays);

        return response()->json([
            'success' => true,
            'preview' => $preview,
        ]);
    }

    /**
     * Execute retention-based log file deletion.
     */
    public function executeRetention(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'retention_days' => ['required', 'integer', 'min:1'],
            'archive' => ['nullable', 'boolean'],
            'confirmed' => ['required', 'boolean'],
        ]);

        if (! $validated['confirmed']) {
            return response()->json([
                'success' => false,
                'message' => 'Retention cleanup cancelled: confirmation not provided.',
            ], 422);
        }

        $retentionDays = (int) $validated['retention_days'];
        $archive = (bool) ($validated['archive'] ?? true);

        $report = $this->cleanupService->executeRetentionCleanup($retentionDays, $archive);

        return response()->json([
            'success' => true,
            'message' => "Retention cleanup completed. Freed {$report['formatted_freed']}.",
            'report' => $report,
        ]);
    }

    /**
     * Safely clear (truncate) a single selected log file.
     */
    public function clearFile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'string'],
            'archive' => ['nullable', 'boolean'],
            'confirmed' => ['required', 'boolean'],
        ]);

        if (! $validated['confirmed']) {
            return response()->json([
                'success' => false,
                'message' => 'File clear operation cancelled: confirmation not provided.',
            ], 422);
        }

        $logFile = $this->fileManager->resolveFile($validated['file']);
        $archive = (bool) ($validated['archive'] ?? false);

        $report = $this->cleanupService->clearFile($logFile, $archive);

        return response()->json([
            'success' => true,
            'message' => "Successfully cleared {$logFile->filename}. Freed {$report['formatted_cleared']}.",
            'report' => $report,
        ]);
    }
}
