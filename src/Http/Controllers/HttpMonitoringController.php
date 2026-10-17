<?php

namespace MehrabHossain\LogLens\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use MehrabHossain\LogLens\Contracts\MetricsStoreInterface;
use MehrabHossain\LogLens\LogLens;

class HttpMonitoringController extends Controller
{
    public function __construct(
        protected MetricsStoreInterface $store
    ) {
    }

    /**
     * Render the HTTP performance monitoring dashboard page.
     */
    public function index(Request $request): View
    {
        $summary = $this->store->getHttpSummary();

        return view('loglens::http.index', [
            'version' => LogLens::version(),
            'environment' => app()->environment(),
            'summary' => $summary,
            'is_enabled' => (bool) config('loglens.http.enabled', false),
            'slow_threshold' => config('loglens.http.slow_request_threshold_ms', 1000),
        ]);
    }

    /**
     * Return HTTP metrics as JSON for API consumption and live dashboard refresh.
     */
    public function api(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'metrics' => $this->store->getHttpSummary(),
        ]);
    }
}
