<?php

namespace MehrabHossain\LogLens\Services\Apache;

use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use MehrabHossain\LogLens\Contracts\ApacheCollectorInterface;
use MehrabHossain\LogLens\Contracts\ApacheMetricsStoreInterface;
use MehrabHossain\LogLens\Exceptions\LogLensSecurityException;
use MehrabHossain\LogLens\LogLens;
use Throwable;

class ApacheStatusCollector implements ApacheCollectorInterface
{
    protected ?array $previousReading = null;
    protected ?float $lastSuccessfulCollection = null;
    protected int $consecutiveFailures = 0;

    public function __construct(
        protected ApacheMetricsStoreInterface $store,
        protected ApacheModStatusParser $parser,
        protected ApacheRateCalculator $rateCalculator
    ) {
    }

    /**
     * Perform a single collection cycle from the configured Apache mod_status endpoint.
     */
    public function collect(): array
    {
        if (! $this->isEnabled()) {
            return [
                'success' => false,
                'error' => 'Apache monitoring is disabled.',
            ];
        }

        $url = (string) config('loglens.apache.status_url', 'http://127.0.0.1/server-status?auto');
        $timeout = (float) config('loglens.apache.timeout_seconds', 1.0);

        try {
            $this->validateUrl($url);

            $response = Http::timeout($timeout)
                ->withHeaders([
                    'User-Agent' => 'LogLens-Apache-Collector/1.0',
                    'Accept' => 'text/plain',
                ])
                ->get($url);

            if (! $response->successful()) {
                throw new Exception("HTTP request failed with status {$response->status()} ({$response->reason()}).");
            }

            $rawBody = $response->body();
            $parsed = $this->parser->parse($rawBody);

            $timestamp = microtime(true);
            $parsed['timestamp'] = $timestamp;

            // Retrieve previous reading (from memory or from the store)
            $previous = $this->previousReading ?? $this->store->getLatest();

            // Calculate instantaneous rates (RPS, BPS) and detect restarts
            $rates = $this->rateCalculator->calculate($parsed, $previous);

            $snapshot = array_merge($parsed, [
                'rps' => $rates['rps'],
                'bps' => $rates['bps'],
                'elapsed_seconds' => $rates['elapsed_seconds'],
                'restart_detected' => $rates['restart_detected'],
                'counter_reset_detected' => $rates['counter_reset_detected'],
                'calculation_valid' => $rates['calculation_valid'],
                'collected_at' => date('Y-m-d H:i:s', (int) $timestamp),
            ]);

            // Persist to shared store
            $this->store->record($snapshot);

            $this->previousReading = $snapshot;
            $this->lastSuccessfulCollection = $timestamp;
            $this->consecutiveFailures = 0;

            return [
                'success' => true,
                'metrics' => $snapshot,
            ];
        } catch (LogLensSecurityException $e) {
            $this->handleFailure("Security error: {$e->getMessage()}");

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'security_violation' => true,
            ];
        } catch (ConnectionException $e) {
            $this->handleFailure("Connection error: Unable to reach Apache endpoint ({$e->getMessage()}).");

            return [
                'success' => false,
                'error' => 'Unable to connect to Apache status endpoint. Verify Apache is running and mod_status is enabled.',
            ];
        } catch (InvalidArgumentException $e) {
            $this->handleFailure("Parser error: {$e->getMessage()}");

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        } catch (Throwable $e) {
            $this->handleFailure("Collector failure: {$e->getMessage()}");

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get the current status and health of the collector.
     */
    public function getStatus(): array
    {
        $enabled = $this->isEnabled();
        $staleThreshold = (int) config('loglens.apache.stale_threshold_seconds', 5);
        $isStale = $this->store->isStale($staleThreshold);
        $latest = $this->store->getLatest();

        $state = 'standby';
        if ($enabled) {
            if ($this->consecutiveFailures > 0) {
                $state = 'error';
            } elseif ($latest === null) {
                $state = 'waiting_data';
            } elseif ($isStale) {
                $state = 'stale';
            } else {
                $state = 'active';
            }
        }

        return [
            'enabled' => $enabled,
            'state' => $state,
            'is_stale' => $isStale,
            'last_successful_collection' => $this->lastSuccessfulCollection,
            'consecutive_failures' => $this->consecutiveFailures,
            'last_error' => $this->store->getLastError(),
            'status_url' => $this->maskUrl((string) config('loglens.apache.status_url', 'http://127.0.0.1/server-status?auto')),
            'collection_interval_seconds' => (int) config('loglens.apache.collection_interval_seconds', 1),
            'stale_threshold_seconds' => $staleThreshold,
        ];
    }

    /**
     * Determine if Apache monitoring is enabled.
     */
    public function isEnabled(): bool
    {
        return LogLens::isEnabled() && (bool) config('loglens.apache.enabled', false);
    }

    /**
     * Handle collector failures safely.
     */
    protected function handleFailure(string $errorMessage): void
    {
        $this->consecutiveFailures++;
        $this->store->recordError($errorMessage);
    }

    /**
     * Validate target Apache status URL to prevent SSRF and arbitrary host connections.
     *
     * @throws LogLensSecurityException
     */
    public function validateUrl(string $url): void
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new LogLensSecurityException('Invalid Apache status URL format.');
        }

        $parsed = parse_url($url);
        if (! $parsed || empty($parsed['scheme']) || empty($parsed['host'])) {
            throw new LogLensSecurityException('Malformed Apache status URL.');
        }

        $scheme = strtolower($parsed['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new LogLensSecurityException("Unsupported URL scheme '{$scheme}'. Only http and https are allowed.");
        }

        if (! empty($parsed['user']) || ! empty($parsed['pass'])) {
            throw new LogLensSecurityException('Embedded user credentials in status URL are prohibited.');
        }

        $host = strtolower($parsed['host']);
        $allowedHosts = config('loglens.apache.allowed_hosts', [
            '127.0.0.1',
            'localhost',
            '::1',
        ]);

        if (is_array($allowedHosts) && ! empty($allowedHosts) && ! in_array($host, $allowedHosts, true)) {
            throw new LogLensSecurityException("Host '{$host}' is not in the allowed Apache status hosts list.");
        }
    }

    /**
     * Mask sensitive parts of the status URL for display in UI.
     */
    protected function maskUrl(string $url): string
    {
        $parsed = parse_url($url);
        if (! $parsed || empty($parsed['host'])) {
            return $url;
        }

        $port = isset($parsed['port']) ? ":{$parsed['port']}" : '';
        $path = $parsed['path'] ?? '/server-status';
        $query = isset($parsed['query']) ? "?{$parsed['query']}" : '';

        return "{$parsed['scheme']}://{$parsed['host']}{$port}{$path}{$query}";
    }
}
