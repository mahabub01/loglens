<?php

namespace MehrabHossain\LogLens\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use MehrabHossain\LogLens\Contracts\AlertManagerInterface;
use MehrabHossain\LogLens\Contracts\SystemMetricsCollectorInterface;
use MehrabHossain\LogLens\LogLens;

class SystemHealthController extends Controller
{
    public function __construct(
        protected SystemMetricsCollectorInterface $collector,
        protected AlertManagerInterface $alertManager
    ) {
    }

    /**
     * Render the Server Health dashboard view.
     */
    public function index(Request $request): View
    {
        $metrics = $this->collector->collect();
        $alerts = $this->alertManager->evaluate([
            'system' => $metrics,
        ]);

        return view('loglens::server.index', [
            'version' => LogLens::version(),
            'environment' => app()->environment(),
            'metrics' => $metrics,
            'platform' => $metrics['platform'],
            'alerts' => $alerts,
            'is_enabled' => $this->collector->isEnabled(),
        ]);
    }

    /**
     * Return system metrics as JSON for API consumers and live charts.
     */
    public function api(Request $request): JsonResponse
    {
        $metrics = $this->collector->collect();
        $alerts = $this->alertManager->evaluate([
            'system' => $metrics,
        ]);

        return response()->json([
            'success' => true,
            'metrics' => $metrics,
            'alerts' => $alerts,
            'timestamp' => microtime(true),
        ]);
    }
}
