<?php

namespace MehrabHossain\LogLens\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CorrelationIdManager
{
    protected ?string $currentCorrelationId = null;

    /**
     * Resolve, validate or generate a correlation ID for the incoming request.
     */
    public function resolve(Request $request): string
    {
        $incomingId = $request->header('X-Correlation-ID')
            ?? $request->header('X-Request-ID');

        if ($incomingId && $this->isValid($incomingId)) {
            $this->currentCorrelationId = $incomingId;
        } else {
            $this->currentCorrelationId = (string) Str::uuid();
        }

        // Propagate to Laravel logger context if available
        if (class_exists(Log::class) && method_exists(Log::class, 'withContext')) {
            try {
                Log::withContext(['correlation_id' => $this->currentCorrelationId]);
            } catch (\Throwable) {
                // Ignore context errors in test/minimal environments
            }
        }

        return $this->currentCorrelationId;
    }

    /**
     * Get the current active correlation ID.
     */
    public function current(): string
    {
        return $this->currentCorrelationId ??= (string) Str::uuid();
    }

    /**
     * Attach the correlation ID to the outgoing response headers.
     */
    public function attachToResponse(Response $response): void
    {
        if ($this->currentCorrelationId) {
            $response->headers->set('X-Correlation-ID', $this->currentCorrelationId);
        }
    }

    /**
     * Reset the active correlation ID (critical for Octane / persistent workers).
     */
    public function reset(): void
    {
        $this->currentCorrelationId = null;
    }

    /**
     * Validate incoming ID format to prevent header injection or malformed data.
     */
    protected function isValid(mixed $id): bool
    {
        if (! is_string($id)) {
            return false;
        }

        $length = strlen($id);
        if ($length < 4 || $length > 64) {
            return false;
        }

        return (bool) preg_match('/^[a-zA-Z0-9_\-\.]+$/', $id);
    }
}
