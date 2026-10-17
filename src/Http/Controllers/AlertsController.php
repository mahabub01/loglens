<?php

namespace MehrabHossain\LogLens\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use MehrabHossain\LogLens\Contracts\AlertManagerInterface;
use MehrabHossain\LogLens\Contracts\QueueMetricsCollectorInterface;
use MehrabHossain\LogLens\Contracts\SystemMetricsCollectorInterface;

class AlertsController extends Controller
{
    public function __construct(
        protected AlertManagerInterface $alertManager,
        protected SystemMetricsCollectorInterface $systemCollector,
        protected QueueMetricsCollectorInterface $queueCollector
    ) {
    }

    /**
     * Return active system and performance alerts as JSON.
     */
    public function api(Request $request): JsonResponse
    {
        $context = [
            'system' => $this->systemCollector->collect(),
            'queue' => $this->queueCollector->getSummary(),
        ];

        $alerts = $this->alertManager->evaluate($context);

        return response()->json([
            'success' => true,
            'alerts' => $alerts,
            'timestamp' => microtime(true),
        ]);
    }
}
