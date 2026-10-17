<?php

namespace MehrabHossain\LogLens\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use MehrabHossain\LogLens\Contracts\LogFileManagerInterface;
use MehrabHossain\LogLens\LogLens;

class DashboardController extends Controller
{
    public function __construct(
        protected ?LogFileManagerInterface $fileManager = null
    ) {
    }

    /**
     * Render the main LogLens dashboard.
     */
    public function index(Request $request): View
    {
        $logFiles = $this->fileManager ? $this->fileManager->getLogFiles() : [];
        $logFilesCount = count($logFiles);
        $totalLogBytes = array_sum(array_map(fn ($f) => $f->sizeInBytes, $logFiles));

        $collectors = [
            'logs' => [
                'name' => 'Log File Analyzer',
                'status' => 'active',
                'label' => "Active ({$logFilesCount} files)",
                'badge' => 'success',
                'description' => 'Bounded streaming reader, Monolog parser, live tail, error grouping & cleanup.',
            ],
            'database' => [
                'name' => 'Database Query Observer',
                'status' => config('loglens.database.enabled') ? 'standby' : 'disabled',
                'label' => config('loglens.database.enabled') ? 'Strict Mode (Zero Queries)' : 'Disabled',
                'badge' => config('loglens.database.enabled') ? 'info' : 'secondary',
                'description' => 'Will passively observe DB::listen events with zero extra queries in Phase 3.',
            ],
            'http' => [
                'name' => 'HTTP Performance Monitor',
                'status' => config('loglens.http.enabled') ? 'active' : 'disabled',
                'label' => config('loglens.http.enabled') ? 'Active' : 'Disabled',
                'badge' => config('loglens.http.enabled') ? 'success' : 'secondary',
                'description' => 'HTTP request rate, response time, and p95/p99 latency tracking.',
            ],
            'apache' => [
                'name' => 'Apache mod_status Monitor',
                'status' => config('loglens.apache.enabled') ? 'active' : 'disabled',
                'label' => config('loglens.apache.enabled') ? 'Active' : 'Disabled',
                'badge' => config('loglens.apache.enabled') ? 'success' : 'secondary',
                'description' => 'Shared collector for RPS, BPS, and worker status in Phase 4.',
            ],
            'system' => [
                'name' => 'Host Resource Monitor',
                'status' => config('loglens.system.enabled') ? 'active' : 'disabled',
                'label' => config('loglens.system.enabled') ? 'Active' : 'Disabled',
                'badge' => config('loglens.system.enabled') ? 'success' : 'secondary',
                'description' => 'Safe Linux host CPU, memory, and disk health metrics in Phase 5.',
            ],
            'queue' => [
                'name' => 'Queue & Scheduler Monitor',
                'status' => 'disabled',
                'label' => 'Disabled',
                'badge' => 'secondary',
                'description' => 'Optional background job and scheduler monitoring in Phase 5.',
            ],
        ];

        return view('loglens::dashboard', [
            'version' => LogLens::version(),
            'environment' => app()->environment(),
            'app_name' => config('app.name', 'Laravel'),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'collectors' => $collectors,
            'config' => config('loglens'),
            'log_files_count' => $logFilesCount,
            'log_total_bytes' => $totalLogBytes,
        ]);
    }
}
