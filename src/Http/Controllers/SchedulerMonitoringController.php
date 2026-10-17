<?php

namespace MehrabHossain\LogLens\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use MehrabHossain\LogLens\Contracts\SchedulerMetricsCollectorInterface;
use MehrabHossain\LogLens\LogLens;

class SchedulerMonitoringController extends Controller
{
    public function __construct(
        protected SchedulerMetricsCollectorInterface $collector
    ) {
    }

    /**
     * Render the Scheduler tasks monitoring dashboard view.
     */
    public function index(Request $request): View
    {
        $summary = $this->collector->getSummary();

        return view('loglens::scheduler.index', [
            'version' => LogLens::version(),
            'environment' => app()->environment(),
            'summary' => $summary,
            'recent_tasks' => $this->collector->getRecentTasks(25),
            'is_enabled' => $this->collector->isEnabled(),
        ]);
    }

    /**
     * Return scheduler metrics as JSON.
     */
    public function api(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'summary' => $this->collector->getSummary(),
            'recent_tasks' => $this->collector->getRecentTasks(25),
            'timestamp' => microtime(true),
        ]);
    }
}
