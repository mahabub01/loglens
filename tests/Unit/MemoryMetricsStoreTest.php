<?php

namespace MehrabHossain\LogLens\Tests\Unit;

use MehrabHossain\LogLens\Services\MemoryMetricsStore;
use MehrabHossain\LogLens\Tests\TestCase;

class MemoryMetricsStoreTest extends TestCase
{
    protected MemoryMetricsStore $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->store = new MemoryMetricsStore(maxSamples: 10);
    }

    public function test_empty_store_returns_safe_defaults(): void
    {
        $http = $this->store->getHttpSummary();
        $this->assertSame(0, $http['total_requests']);
        $this->assertSame(0.0, $http['avg_response_time_ms']);
        $this->assertSame(0.0, $http['median_response_time_ms']);
        $this->assertSame(0.0, $http['p95_response_time_ms']);
        $this->assertSame(0.0, $http['p99_response_time_ms']);
        $this->assertSame(0, $http['slow_request_count']);
        $this->assertEmpty($http['recent_requests']);

        $db = $this->store->getDatabaseSummary();
        $this->assertSame(0, $db['total_queries']);
        $this->assertSame(0.0, $db['total_duration_ms']);
        $this->assertSame(0.0, $db['avg_duration_ms']);
        $this->assertSame(0, $db['slow_query_count']);
        $this->assertSame(0, $db['n_plus_one_warnings_count']);
        $this->assertEmpty($db['slow_queries']);
    }

    public function test_records_http_requests_and_calculates_percentiles(): void
    {
        // Add 5 samples with known durations
        $durations = [10.0, 20.0, 30.0, 40.0, 100.0];
        foreach ($durations as $d) {
            $this->store->recordHttp([
                'duration_ms' => $d,
                'status_code' => 200,
                'route' => 'test.route',
                'is_slow' => $d >= 50.0,
                'correlation_id' => 'uuid',
            ]);
        }

        $summary = $this->store->getHttpSummary();

        $this->assertSame(5, $summary['total_requests']);
        $this->assertEquals(40.0, $summary['avg_response_time_ms']); // (10+20+30+40+100)/5 = 40.0
        $this->assertEquals(30.0, $summary['median_response_time_ms']); // middle element
        $this->assertSame(1, $summary['slow_request_count']);
        $this->assertSame(5, $summary['status_distribution']['2xx']);
    }

    public function test_status_code_distribution_buckets(): void
    {
        $codes = [200, 201, 301, 400, 404, 500, 503];
        foreach ($codes as $code) {
            $this->store->recordHttp([
                'duration_ms' => 10.0,
                'status_code' => $code,
                'route' => 'test',
                'is_slow' => false,
                'correlation_id' => 'uuid',
            ]);
        }

        $dist = $this->store->getHttpSummary()['status_distribution'];

        $this->assertSame(2, $dist['2xx']);
        $this->assertSame(1, $dist['3xx']);
        $this->assertSame(2, $dist['4xx']);
        $this->assertSame(2, $dist['5xx']);
    }

    public function test_bounded_memory_evicts_oldest_items_fifo(): void
    {
        // Max samples is 10 for this test store
        for ($i = 1; $i <= 15; $i++) {
            $this->store->recordHttp([
                'duration_ms' => (float) $i,
                'status_code' => 200,
                'route' => "route.{$i}",
                'is_slow' => false,
                'correlation_id' => "corr-{$i}",
            ]);
        }

        $summary = $this->store->getHttpSummary();

        // Exactly 10 items kept in memory
        $this->assertCount(10, $summary['recent_requests']);
        // The newest item is route.15 (recent_requests is reversed, so first item is route.15)
        $this->assertSame('route.15', $summary['recent_requests'][0]['route']);
        // The oldest item still present should be route.6
        $this->assertSame('route.6', end($summary['recent_requests'])['route']);
    }

    public function test_records_database_queries_and_connection_distribution(): void
    {
        $this->store->recordQuery([
            'duration_ms' => 25.0,
            'connection' => 'mysql_read',
            'is_slow' => false,
            'fingerprint' => 'fp1',
            'sql' => 'SELECT 1',
        ]);

        $this->store->recordQuery([
            'duration_ms' => 600.0,
            'connection' => 'mysql_write',
            'is_slow' => true,
            'fingerprint' => 'fp2',
            'sql' => 'UPDATE users SET active = ?',
        ]);

        $summary = $this->store->getDatabaseSummary();

        $this->assertSame(2, $summary['total_queries']);
        $this->assertEquals(625.0, $summary['total_duration_ms']);
        $this->assertEquals(312.5, $summary['avg_duration_ms']);
        $this->assertSame(1, $summary['slow_query_count']);
        $this->assertSame(1, $summary['connection_distribution']['mysql_read']);
        $this->assertSame(1, $summary['connection_distribution']['mysql_write']);
        $this->assertCount(1, $summary['slow_queries']);
    }

    public function test_records_request_summary_and_potential_n_plus_one_alerts(): void
    {
        $this->store->recordRequestSummary([
            'n_plus_one_alerts' => [
                [
                    'fingerprint' => 'fp-posts',
                    'count' => 15,
                    'total_duration_ms' => 45.0,
                    'sample_sql' => 'SELECT * FROM posts WHERE user_id = ?',
                    'route' => 'users.index',
                    'hint' => 'Eager load relationships.',
                ],
            ],
        ]);

        $summary = $this->store->getDatabaseSummary();

        $this->assertSame(1, $summary['n_plus_one_warnings_count']);
        $this->assertCount(1, $summary['potential_n_plus_one']);
        $this->assertSame('fp-posts', $summary['potential_n_plus_one'][0]['fingerprint']);
    }

    public function test_reset_clears_all_metrics(): void
    {
        $this->store->recordHttp(['duration_ms' => 10.0, 'status_code' => 200]);
        $this->store->recordQuery(['duration_ms' => 10.0, 'connection' => 'sqlite', 'is_slow' => false]);

        $this->store->reset();

        $this->assertSame(0, $this->store->getHttpSummary()['total_requests']);
        $this->assertSame(0, $this->store->getDatabaseSummary()['total_queries']);
    }
}
