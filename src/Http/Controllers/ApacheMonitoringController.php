<?php

namespace MehrabHossain\LogLens\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use MehrabHossain\LogLens\Contracts\ApacheCollectorInterface;
use MehrabHossain\LogLens\Contracts\ApacheMetricsStoreInterface;
use MehrabHossain\LogLens\LogLens;

class ApacheMonitoringController extends Controller
{
    public function __construct(
        protected ApacheCollectorInterface $collector,
        protected ApacheMetricsStoreInterface $store
    ) {
    }

    /**
     * Render the Apache mod_status monitoring dashboard page.
     */
    public function index(Request $request): View
    {
        $status = $this->collector->getStatus();
        $latest = $this->store->getLatest();
        $history = $this->store->getHistory(60);

        return view('loglens::apache.index', [
            'version' => LogLens::version(),
            'environment' => app()->environment(),
            'status' => $status,
            'latest' => $latest,
            'history' => $history,
            'is_enabled' => $this->collector->isEnabled(),
            'is_stale' => $this->store->isStale((int) config('loglens.apache.stale_threshold_seconds', 5)),
            'poll_interval_ms' => (int) config('loglens.apache.dashboard_refresh_interval_ms', 2000),
        ]);
    }

    /**
     * Return Apache metrics as JSON for live dashboard refresh.
     *
     * Note: This endpoint reads exclusively from the shared metrics store and
     * NEVER triggers a direct HTTP request to Apache mod_status.
     */
    public function api(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'status' => $this->collector->getStatus(),
            'latest' => $this->store->getLatest(),
            'history' => $this->store->getHistory(60),
            'timestamp' => microtime(true),
        ]);
    }
}
