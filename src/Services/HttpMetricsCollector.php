<?php

namespace MehrabHossain\LogLens\Services;

use Illuminate\Http\Request;
use MehrabHossain\LogLens\Contracts\HttpMetricsCollectorInterface;
use MehrabHossain\LogLens\Contracts\MetricsStoreInterface;
use MehrabHossain\LogLens\LogLens;
use MehrabHossain\LogLens\Support\CorrelationIdManager;
use MehrabHossain\LogLens\Support\RequestContext;
use Symfony\Component\HttpFoundation\Response;

class HttpMetricsCollector implements HttpMetricsCollectorInterface
{
    protected ?float $requestStartTime = null;

    public function __construct(
        protected MetricsStoreInterface $store,
        protected CorrelationIdManager $correlationIdManager,
        protected RequestContext $requestContext
    ) {
    }

    /**
     * Start timing an incoming HTTP request and initialize request context.
     */
    public function start(Request $request): void
    {
        if (! $this->isEnabled() || $this->shouldIgnore($request)) {
            return;
        }

        $this->requestStartTime = microtime(true);
        $correlationId = $this->correlationIdManager->resolve($request);
        $routeName = $request->route()?->getName() ?? $request->path();

        $this->requestContext->start($correlationId, $routeName, $this->requestStartTime);
    }

    /**
     * Finalize request observation, capture metrics and aggregate results.
     */
    public function terminate(Request $request, Response $response): void
    {
        if (! $this->isEnabled() || $this->shouldIgnore($request)) {
            $this->cleanup();

            return;
        }

        try {
            $this->correlationIdManager->attachToResponse($response);

            $durationMs = $this->requestStartTime
                ? round((microtime(true) - $this->requestStartTime) * 1000, 2)
                : 0.0;

            $statusCode = $response->getStatusCode();
            $routeName = $request->route()?->getName() ?? $request->path();
            $correlationId = $this->correlationIdManager->current();

            $slowThreshold = (float) config('loglens.http.slow_request_threshold_ms', 1000);
            $isSlow = $durationMs >= $slowThreshold;

            // 1. Record individual request metric
            $this->store->recordHttp([
                'timestamp' => microtime(true),
                'duration_ms' => $durationMs,
                'status_code' => $statusCode,
                'route' => $routeName,
                'is_slow' => $isSlow,
                'correlation_id' => $correlationId,
            ]);

            // 2. Record full request summary (including query counts and N+1 alerts)
            $this->store->recordRequestSummary($this->requestContext->summary());
        } finally {
            $this->cleanup();
        }
    }

    /**
     * Clean up request-scoped state.
     */
    protected function cleanup(): void
    {
        $this->requestStartTime = null;
        $this->requestContext->reset();
        $this->correlationIdManager->reset();
    }

    /**
     * Determine if HTTP metrics collection is enabled.
     */
    public function isEnabled(): bool
    {
        return LogLens::isEnabled() && (bool) config('loglens.http.enabled', true);
    }

    /**
     * Determine if the given request path should be ignored.
     */
    protected function shouldIgnore(Request $request): bool
    {
        $ignorePatterns = config('loglens.http.ignore_paths', [
            'loglens*',
            'telescope*',
            'horizon*',
            'pulse*',
            '_debugbar*',
        ]);

        foreach ($ignorePatterns as $pattern) {
            if ($request->is($pattern)) {
                return true;
            }
        }

        return false;
    }
}
