<?php

namespace MehrabHossain\LogLens\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use MehrabHossain\LogLens\Contracts\DatabaseMetricsCollectorInterface;
use MehrabHossain\LogLens\Contracts\MetricsStoreInterface;
use MehrabHossain\LogLens\Http\Middleware\LogLensHttpMonitoringMiddleware;
use MehrabHossain\LogLens\Tests\TestCase;

class DatabaseMonitoringTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Create a temporary in-memory table for testing business queries
        Schema::create('test_articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->integer('author_id');
            $table->timestamps();
        });

        // Register a test route that executes database queries
        Route::get('/monitored/db-queries', function () {
            DB::table('test_articles')->insert(['title' => 'Article 1', 'author_id' => 10]);
            DB::table('test_articles')->where('id', 1)->first();
            return response('DB OK');
        })->middleware(LogLensHttpMonitoringMiddleware::class)->name('monitored.db');

        // Route simulating an N+1 query loop
        Route::get('/monitored/n-plus-one', function () {
            for ($i = 1; $i <= 4; $i++) {
                DB::table('test_articles')->where('author_id', $i)->get();
            }
            return response('N+1 loop completed');
        })->middleware(LogLensHttpMonitoringMiddleware::class)->name('monitored.n1');
    }

    public function test_unauthorized_user_cannot_access_database_monitoring(): void
    {
        $this->enablePackage();
        config(['loglens.auth.allow_local_by_default' => false]);

        $response = $this->get('/loglens/database');
        $response->assertStatus(403);

        $apiResponse = $this->get('/loglens/api/metrics/database');
        $apiResponse->assertStatus(403);
    }

    public function test_authorized_user_can_view_database_monitoring_page(): void
    {
        $this->enablePackage();
        $this->authorizeAccess();

        $response = $this->get('/loglens/database');

        $response->assertStatus(200);
        $response->assertSee('Database Query Observation');
        $response->assertSee('Strict Zero-Extra-Query Mode Active');
        $response->assertSee('Observed Queries');
        $response->assertSee('Aggregate Time');
        $response->assertSee('Avg Query Time');
        $response->assertSee('Potential N+1');
        $response->assertSee('Database Server Metrics Limitation');
    }

    public function test_database_metrics_api_returns_json(): void
    {
        $this->enablePackage();
        $this->authorizeAccess();

        $response = $this->get('/loglens/api/metrics/database');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'strict_mode',
            'extra_queries_executed',
            'metrics' => [
                'total_queries',
                'total_duration_ms',
                'avg_duration_ms',
                'slow_query_count',
                'n_plus_one_warnings_count',
                'connection_distribution',
                'slow_queries',
                'potential_n_plus_one',
            ],
        ]);
        $response->assertJson([
            'success' => true,
            'strict_mode' => true,
            'extra_queries_executed' => 0,
        ]);
    }

    public function test_passively_observes_business_queries_with_zero_extra_queries(): void
    {
        $this->enablePackage();
        config([
            'loglens.database.enabled' => true,
            'loglens.database.strict_mode' => true,
        ]);

        /** @var MetricsStoreInterface $store */
        $store = app(MetricsStoreInterface::class);
        $store->reset();

        // Execute query on the application's default testing connection
        DB::select('SELECT 1 as test_val');

        $summary = $store->getDatabaseSummary();

        $this->assertGreaterThanOrEqual(1, $summary['total_queries']);
        $this->assertArrayHasKey('testing', $summary['connection_distribution']);
    }

    public function test_query_bindings_are_not_captured_by_default(): void
    {
        $this->enablePackage();
        config([
            'loglens.database.enabled' => true,
            'loglens.database.collect_bindings' => false,
        ]);

        /** @var MetricsStoreInterface $store */
        $store = app(MetricsStoreInterface::class);
        $store->reset();

        DB::table('test_articles')->insert([
            'title' => 'Secret Article Title 999',
            'author_id' => 77,
        ]);

        $summary = $store->getDatabaseSummary();
        $this->assertNotEmpty($summary);

        // Verify recent queries stored in memory do NOT contain the raw binding
        $ref = new \ReflectionClass($store);
        $prop = $ref->getProperty('recentQueries');
        $prop->setAccessible(true);
        $queries = $prop->getValue($store);

        $lastQuery = end($queries);
        $this->assertArrayNotHasKey('bindings', $lastQuery);
        $this->assertStringNotContainsString('Secret Article Title 999', $lastQuery['sql']);
    }

    public function test_detects_n_plus_one_heuristic_in_monitored_request(): void
    {
        $this->enablePackage();
        config([
            'loglens.http.enabled' => true,
            'loglens.database.enabled' => true,
        ]);

        /** @var MetricsStoreInterface $store */
        $store = app(MetricsStoreInterface::class);
        $store->reset();

        $response = $this->get('/monitored/n-plus-one');
        $response->assertStatus(200);

        $summary = $store->getDatabaseSummary();

        $this->assertGreaterThanOrEqual(1, $summary['n_plus_one_warnings_count']);
        $alert = $summary['potential_n_plus_one'][0];
        $this->assertSame(4, $alert['count']);
        $this->assertSame('monitored.n1', $alert['route']);
        $this->assertStringContainsString('Consider eager loading', $alert['hint']);
    }

    public function test_disabled_database_monitoring_does_not_record_queries(): void
    {
        $this->enablePackage();
        config(['loglens.database.enabled' => false]);

        /** @var MetricsStoreInterface $store */
        $store = app(MetricsStoreInterface::class);
        $store->reset();

        DB::select('SELECT 42 as disabled_check');

        $summary = $store->getDatabaseSummary();
        $this->assertSame(0, $summary['total_queries']);
    }
}
