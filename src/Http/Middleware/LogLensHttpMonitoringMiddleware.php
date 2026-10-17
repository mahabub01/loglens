<?php

namespace MehrabHossain\LogLens\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use MehrabHossain\LogLens\Contracts\HttpMetricsCollectorInterface;
use Symfony\Component\HttpFoundation\Response;

class LogLensHttpMonitoringMiddleware
{
    public function __construct(
        protected HttpMetricsCollectorInterface $collector
    ) {
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->collector->start($request);

        $response = $next($request);

        // In non-terminable environments (or tests), finalize directly
        if (! app()->runningUnitTests() && ! method_exists($this, 'terminate')) {
            $this->collector->terminate($request, $response);
        }

        return $response;
    }

    /**
     * Handle tasks after the response has been sent to the browser.
     */
    public function terminate(Request $request, Response $response): void
    {
        $this->collector->terminate($request, $response);
    }
}
