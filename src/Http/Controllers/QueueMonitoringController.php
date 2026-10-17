<?php

namespace MehrabHossain\LogLens\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use MehrabHossain\LogLens\Contracts\AlertManagerInterface;
use MehrabHossain\LogLens\Contracts\QueueMetricsCollectorInterface;
use MehrabHossain\LogLens\LogLens;

class QueueMonitoringController extends Controller
{
    public function __construct(
        protected QueueMetricsCollectorInterface $collector,
        protected AlertManagerInterface $alertManager
    ) {
    }

    /**
     * Render the Queue performance monitoring dashboard view.
     */
    public function index(Request $request): View
    {
        $summary = $this->collector->getSummary();
        $alerts = $this->alertManager->evaluate([
            'queue' => $summary,
        ]);

        return view('loglens::queue.index', [
            'version' => LogLens::version(),
            'environment' => app()->environment(),
            'summary' => $summary,
            'recent_failed' => $this->collector->getRecentFailedJobs(20),
            'recent_processed' => $this->collector->getRecentProcessedJobs(20),
            'alerts' => $alerts,
            'is_enabled' => $this->collector->isEnabled(),
        ]);
    }

    /**
     * Return queue metrics as JSON.
     */
    public function api(Request $request): JsonResponse
    {
        $summary = $this->collector->getSummary();
        $alerts = $this->alertManager->evaluate([
            'queue' => $summary,
        ]);

        return response()->json([
            'success' => true,
            'summary' => $summary,
            'recent_failed' => $this->collector->getRecentFailedJobs(20),
            'recent_processed' => $this->collector->getRecentProcessedJobs(20),
            'alerts' => $alerts,
            'timestamp' => microtime(true),
        ]);
    }
}
