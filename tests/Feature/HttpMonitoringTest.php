<?php

namespace MehrabHossain\LogLens\Tests\Feature;

use Illuminate\Support\Facades\Route;
use MehrabHossain\LogLens\Contracts\MetricsStoreInterface;
use MehrabHossain\LogLens\Http\Middleware\LogLensHttpMonitoringMiddleware;
use MehrabHossain\LogLens\Tests\TestCase;

class HttpMonitoringTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Register test routes wrapped with loglens.http middleware
        Route::get('/monitored/fast', function () {
            return response('Fast OK', 200);
        })->middleware(LogLensHttpMonitoringMiddleware::class)->name('monitored.fast');

        Route::get('/monitored/slow', function () {
            usleep(25000); // 25ms delay
            return response('Slow OK', 200);
        })->middleware(LogLensHttpMonitoringMiddleware::class)->name('monitored.slow');

        Route::get('/monitored/error', function () {
            return response('Error Occurred', 500);
        })->middleware(LogLensHttpMonitoringMiddleware::class)->name('monitored.error');
    }

    public function test_unauthorized_user_cannot_access_http_monitoring(): void
    {
        $this->enablePackage();
        config(['loglens.auth.allow_local_by_default' => false]);

        $response = $this->get('/loglens/http');
        $response->assertStatus(403);

        $apiResponse = $this->get('/loglens/api/metrics/http');
        $apiResponse->assertStatus(403);
    }

    public function test_authorized_user_can_view_http_monitoring_page(): void
    {
        $this->enablePackage();
        $this->authorizeAccess();

        $response = $this->get('/loglens/http');

        $response->assertStatus(200);
        $response->assertSee('HTTP Performance Monitoring');
        $response->assertSee('Avg Latency');
        $response->assertSee('p95 Latency');
        $response->assertSee('p99 Latency');
        $response->assertSee('Slow Requests');
    }

    public function test_http_metrics_api_endpoint_returns_json(): void
    {
        $this->enablePackage();
        $this->authorizeAccess();

        $response = $this->get('/loglens/api/metrics/http');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'metrics' => [
                'total_requests',
                'avg_response_time_ms',
                'median_response_time_ms',
                'p95_response_time_ms',
                'p99_response_time_ms',
                'slow_request_count',
                'status_distribution',
                'recent_requests',
                'recent_slow_requests',
            ],
        ]);
        $response->assertJson(['success' => true]);
    }

    public function test_middleware_captures_http_requests_and_attaches_correlation_id(): void
    {
        $this->enablePackage();
        config(['loglens.http.enabled' => true]);

        /** @var MetricsStoreInterface $store */
        $store = app(MetricsStoreInterface::class);
        $store->reset();

        $response = $this->get('/monitored/fast');

        $response->assertStatus(200);
        $this->assertTrue($response->headers->has('X-Correlation-ID'));
        $correlationId = $response->headers->get('X-Correlation-ID');
        $this->assertNotEmpty($correlationId);

        $summary = $store->getHttpSummary();
        $this->assertSame(1, $summary['total_requests']);
        $this->assertSame(1, $summary['status_distribution']['2xx']);
        $this->assertSame('monitored.fast', $summary['recent_requests'][0]['route']);
        $this->assertSame($correlationId, $summary['recent_requests'][0]['correlation_id']);
    }

    public function test_middleware_detects_slow_requests_based_on_threshold(): void
    {
        $this->enablePackage();
        config([
            'loglens.http.enabled' => true,
            'loglens.http.slow_request_threshold_ms' => 10, // 10ms threshold
        ]);

        /** @var MetricsStoreInterface $store */
        $store = app(MetricsStoreInterface::class);
        $store->reset();

        $response = $this->get('/monitored/slow'); // sleeps 25ms

        $response->assertStatus(200);

        $summary = $store->getHttpSummary();
        $this->assertSame(1, $summary['total_requests']);
        $this->assertSame(1, $summary['slow_request_count']);
        $this->assertCount(1, $summary['recent_slow_requests']);
    }

    public function test_middleware_records_error_status_codes(): void
    {
        $this->enablePackage();
        config(['loglens.http.enabled' => true]);

        /** @var MetricsStoreInterface $store */
        $store = app(MetricsStoreInterface::class);
        $store->reset();

        $response = $this->get('/monitored/error');

        $response->assertStatus(500);

        $summary = $store->getHttpSummary();
        $this->assertSame(1, $summary['total_requests']);
        $this->assertSame(1, $summary['status_distribution']['5xx']);
    }

    public function test_middleware_ignores_configured_paths(): void
    {
        $this->enablePackage();
        $this->authorizeAccess();
        config([
            'loglens.http.enabled' => true,
            'loglens.http.ignore_paths' => ['loglens*'],
        ]);

        /** @var MetricsStoreInterface $store */
        $store = app(MetricsStoreInterface::class);
        $store->reset();

        // Accessing loglens internal dashboard routes should NOT be recorded as application traffic
        $this->get('/loglens/http');

        $summary = $store->getHttpSummary();
        $this->assertSame(0, $summary['total_requests']);
    }

    public function test_does_not_capture_metrics_when_http_monitoring_is_disabled(): void
    {
        $this->enablePackage();
        config(['loglens.http.enabled' => false]);

        /** @var MetricsStoreInterface $store */
        $store = app(MetricsStoreInterface::class);
        $store->reset();

        $this->get('/monitored/fast');

        $summary = $store->getHttpSummary();
        $this->assertSame(0, $summary['total_requests']);
    }
}
