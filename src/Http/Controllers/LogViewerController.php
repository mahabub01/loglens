<?php

namespace MehrabHossain\LogLens\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use MehrabHossain\LogLens\Contracts\LogFileManagerInterface;
use MehrabHossain\LogLens\Contracts\LogReaderInterface;
use MehrabHossain\LogLens\LogLens;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LogViewerController extends Controller
{
    public function __construct(
        protected LogFileManagerInterface $fileManager,
        protected LogReaderInterface $reader
    ) {
    }

    /**
     * Display the log file browser and entry viewer.
     */
    public function index(Request $request): View
    {
        $files = $this->fileManager->getLogFiles();
        $selectedIdentifier = $request->input('file');

        $selectedFile = null;
        if (! empty($selectedIdentifier) && isset($files[$selectedIdentifier])) {
            $selectedFile = $files[$selectedIdentifier];
        } elseif (! empty($files)) {
            $selectedFile = reset($files);
        }

        $page = max(1, (int) $request->input('page', 1));
        $perPage = min(200, max(10, (int) $request->input('per_page', 50)));
        $severity = $request->input('level', 'ALL');
        $searchQuery = $request->input('q');
        $dateFrom = $request->input('from');
        $dateTo = $request->input('to');

        $result = [
            'entries' => [],
            'page' => $page,
            'per_page' => $perPage,
            'total_scanned' => 0,
            'has_more' => false,
            'file_size' => 0,
        ];

        if ($selectedFile) {
            $result = $this->reader->readPaginated(
                file: $selectedFile,
                page: $page,
                perPage: $perPage,
                severity: $severity,
                searchQuery: $searchQuery,
                dateFrom: $dateFrom,
                dateTo: $dateTo
            );
        }

        return view('loglens::logs.index', [
            'version' => LogLens::version(),
            'environment' => app()->environment(),
            'files' => $files,
            'selectedFile' => $selectedFile,
            'entries' => $result['entries'],
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'has_more' => $result['has_more'],
                'total_scanned' => $result['total_scanned'],
            ],
            'filters' => [
                'level' => $severity,
                'q' => $searchQuery,
                'from' => $dateFrom,
                'to' => $dateTo,
            ],
        ]);
    }

    /**
     * Securely download an authorized log file.
     */
    public function download(Request $request, string $file): BinaryFileResponse
    {
        $logFile = $this->fileManager->resolveFile($file);

        return response()->download(
            $logFile->realPath,
            $logFile->filename,
            ['Content-Type' => 'text/plain; charset=UTF-8']
        );
    }
}
