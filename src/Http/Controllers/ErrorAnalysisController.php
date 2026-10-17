<?php

namespace MehrabHossain\LogLens\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use MehrabHossain\LogLens\Contracts\LogAnalyzerInterface;
use MehrabHossain\LogLens\Contracts\LogFileManagerInterface;
use MehrabHossain\LogLens\Contracts\LogReaderInterface;
use MehrabHossain\LogLens\LogLens;

class ErrorAnalysisController extends Controller
{
    public function __construct(
        protected LogFileManagerInterface $fileManager,
        protected LogReaderInterface $reader,
        protected LogAnalyzerInterface $analyzer
    ) {
    }

    /**
     * Display the error grouping and frequency analysis dashboard.
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

        $analysis = [
            'total_entries' => 0,
            'error_count' => 0,
            'warning_count' => 0,
            'info_count' => 0,
            'unique_error_groups' => 0,
            'groups' => [],
            'severity_breakdown' => [],
            'timeline' => ['labels' => [], 'counts' => []],
            'top_errors' => [],
        ];

        if ($selectedFile) {
            // Read up to 250 most recent entries for analysis
            $readResult = $this->reader->readPaginated(
                file: $selectedFile,
                page: 1,
                perPage: 250
            );

            if (! empty($readResult['entries'])) {
                $analysis = $this->analyzer->analyze($readResult['entries']);
            }
        }

        return view('loglens::errors.index', [
            'version' => LogLens::version(),
            'environment' => app()->environment(),
            'files' => $files,
            'selectedFile' => $selectedFile,
            'analysis' => $analysis,
        ]);
    }
}
