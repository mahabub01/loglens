<?php

namespace MehrabHossain\LogLens\Contracts;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

interface HttpMetricsCollectorInterface
{
    /**
     * Start timing an incoming HTTP request and initialize request context.
     */
    public function start(Request $request): void;

    /**
     * Finalize request observation, capture metrics and aggregate results.
     */
    public function terminate(Request $request, Response $response): void;

    /**
     * Determine if HTTP metrics collection is currently enabled.
     */
    public function isEnabled(): bool;
}
