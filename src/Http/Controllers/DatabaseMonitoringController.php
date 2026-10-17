<?php

namespace MehrabHossain\LogLens\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use MehrabHossain\LogLens\Contracts\MetricsStoreInterface;
use MehrabHossain\LogLens\LogLens;

class DatabaseMonitoringController extends Controller
{
    public function __construct(
        protected MetricsStoreInterface $store
    ) {
    }

    /**
     * Render the Database Query Observation dashboard page.
     */
    public function index(Request $request): View
    {
        $summary = $this->store->getDatabaseSummary();

        return view('loglens::database.index', [
            'version' => LogLens::version(),
            'environment' => app()->environment(),
            'summary' => $summary,
            'is_enabled' => (bool) config('loglens.database.enabled', true),
            'strict_mode' => (bool) config('loglens.database.strict_mode', true),
            'slow_threshold' => config('loglens.database.slow_query_threshold_ms', 500),
            'collect_bindings' => (bool) config('loglens.database.collect_bindings', false),
        ]);
    }

    /**
     * Return Database metrics as JSON.
     */
    public function api(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'strict_mode' => true,
            'extra_queries_executed' => 0,
            'metrics' => $this->store->getDatabaseSummary(),
        ]);
    }
}
