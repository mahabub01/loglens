<?php

namespace MehrabHossain\LogLens\Tests\Security;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use MehrabHossain\LogLens\Contracts\DatabaseMetricsCollectorInterface;
use MehrabHossain\LogLens\Contracts\HttpMetricsCollectorInterface;
use MehrabHossain\LogLens\Contracts\MetricsStoreInterface;
use MehrabHossain\LogLens\Http\Middleware\LogLensHttpMonitoringMiddleware;
use MehrabHossain\LogLens\Support\CorrelationIdManager;
use MehrabHossain\LogLens\Support\RequestContext;
use MehrabHossain\LogLens\Tests\TestCase;
use Symfony\Component\HttpFoundation\Response;

class ZeroExtraQueryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Create test business table
        Schema::create('business_records', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('secret_data')->nullable();
            $table->timestamps();
        });

        // Route simulating business logic with natural application queries
        Route::get('/app/business-action', function () {
            DB::table('business_records')->insert(['name' => 'Record 1', 'secret_data' => 'super_secret_value']);
            $count = DB::table('business_records')->count();
            $first = DB::table('business_records')->where('id', 1)->first();

            return response()->json([
                'count' => $count,
                'first' => $first->name,
            ]);
        })->middleware(LogLensHttpMonitoringMiddleware::class)->name('app.business');
    }

    public function test_database_listener_does_not_execute_sql(): void
    {
        $this->enablePackage();
        config([
            'loglens.database.enabled' => true,
            'loglens.database.strict_mode' => true,
        ]);

        $executedSqls = [];
        DB::listen(function ($query) use (&$executedSqls) {
            $executedSqls[] = $query->sql;
        });

        // Execute a single natural business query
        DB::select('SELECT 1 as test_val');

        // Exactly 1 SQL statement was observed: the natural business query.
        // The listener itself executed ZERO SQL statements!
        $this->assertCount(1, $executedSqls);
        $this->assertSame('SELECT 1 as test_val', $executedSqls[0]);
    }

    public function test_metrics_aggregation_does_not_insert_database_records(): void
    {
        $this->enablePackage();

        /** @var MetricsStoreInterface $store */
        $store = app(MetricsStoreInterface::class);
        $store->reset();

        $executedQueries = [];
        DB::listen(function ($query) use (&$executedQueries) {
            $executedQueries[] = $query->sql;
        });

        // Record multiple HTTP metrics and query metrics directly into the store
        for ($i = 0; $i < 20; $i++) {
            $store->recordHttp([
                'timestamp' => microtime(true),
                'duration_ms' => 25.5,
                'status_code' => 200,
                'route' => 'test.route',
                'is_slow' => false,
                'correlation_id' => 'uuid-' . $i,
            ]);

            $store->recordQuery([
                'timestamp' => microtime(true),
                'duration_ms' => 12.0,
                'connection' => 'testing',
                'is_slow' => false,
                'fingerprint' => 'fp-abc',
                'sql' => 'SELECT * FROM users WHERE id = ?',
            ]);
        }

        // Generate aggregates
        $httpSummary = $store->getHttpSummary();
        $dbSummary = $store->getDatabaseSummary();

        $this->assertSame(20, $httpSummary['total_requests']);
        $this->assertSame(20, $dbSummary['total_queries']);

        // Strictly 0 SQL queries were executed during recording and aggregation
        $this->assertCount(0, $executedQueries, 'Metrics aggregation must never insert database records.');
    }

    public function test_strict_mode_dashboard_and_api_calls_do_not_query_application_database(): void
    {
        $this->enablePackage();
        $this->authorizeAccess();
        config([
            'loglens.database.enabled' => true,
            'loglens.database.strict_mode' => true,
            'loglens.http.enabled' => true,
        ]);

        $strictRoutes = [
            '/loglens/http',
            '/loglens/database',
            '/loglens/api/metrics/http',
            '/loglens/api/metrics/database',
        ];

        foreach ($strictRoutes as $route) {
            $executedQueries = [];
            DB::listen(function ($query) use (&$executedQueries) {
                $executedQueries[] = $query->sql;
            });

            $response = $this->get($route);
            $response->assertStatus(200);

            $this->assertCount(
                0,
                $executedQueries,
                "Accessing monitoring route '{$route}' must execute strictly zero database queries."
            );
        }
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

        DB::table('business_records')->insert([
            'name' => 'John Doe',
            'secret_data' => 'classified_api_token_xyz',
        ]);

        $summary = $store->getDatabaseSummary();
        $this->assertSame(1, $summary['total_queries']);

        // Inspect recent queries stored
        $ref = new \ReflectionClass($store);
        $prop = $ref->getProperty('recentQueries');
        $prop->setAccessible(true);
        $queries = $prop->getValue($store);

        $this->assertNotEmpty($queries);
        $last = end($queries);

        $this->assertArrayNotHasKey('bindings', $last);
        $this->assertStringNotContainsString('classified_api_token_xyz', $last['sql']);
        $this->assertStringNotContainsString('John Doe', $last['sql']);
    }

    public function test_disabled_collectors_do_not_register_unnecessary_instrumentation(): void
    {
        // When monitoring is disabled
        config([
            'loglens.enabled' => true,
            'loglens.database.enabled' => false,
        ]);

        /** @var DatabaseMetricsCollectorInterface $collector */
        $collector = app(DatabaseMetricsCollectorInterface::class);

        $this->assertFalse($collector->isEnabled());
        $this->assertFalse($collector->isRegistered());

        // Calling register when disabled should not register DB::listen
        $collector->register();
        $this->assertFalse($collector->isRegistered());
    }

    public function test_normal_business_queries_remain_fully_functional(): void
    {
        $this->enablePackage();
        config([
            'loglens.database.enabled' => true,
            'loglens.database.strict_mode' => true,
        ]);

        // Insert
        DB::table('business_records')->insert([
            'name' => 'Business Record 1',
            'secret_data' => 'sec_1',
        ]);

        // Select
        $record = DB::table('business_records')->where('name', 'Business Record 1')->first();
        $this->assertNotNull($record);
        $this->assertSame('Business Record 1', $record->name);

        // Update
        DB::table('business_records')->where('id', $record->id)->update(['name' => 'Updated Name']);
        $updated = DB::table('business_records')->find($record->id);
        $this->assertSame('Updated Name', $updated->name);

        // Transaction & Rollback
        DB::transaction(function () {
            DB::table('business_records')->insert(['name' => 'Tx Record', 'secret_data' => 'tx']);
        });

        $this->assertSame(2, DB::table('business_records')->count());
    }

    public function test_long_running_worker_request_state_is_isolated(): void
    {
        $this->enablePackage();
        config([
            'loglens.http.enabled' => true,
            'loglens.database.enabled' => true,
        ]);

        /** @var HttpMetricsCollectorInterface $httpCollector */
        $httpCollector = app(HttpMetricsCollectorInterface::class);
        /** @var RequestContext $context */
        $context = app(RequestContext::class);
        /** @var CorrelationIdManager $idManager */
        $idManager = app(CorrelationIdManager::class);

        // Simulate Request 1 (worker request 1)
        $req1 = Request::create('/order/checkout', 'POST');
        $httpCollector->start($req1);
        $corrId1 = $idManager->current();

        $context->recordQuery('fp-order', 15.0, false, 'SELECT * FROM orders');
        $this->assertSame(1, $context->getQueryCount());
        $this->assertSame($corrId1, $context->getCorrelationId());

        $res1 = new Response('OK', 200);
        $httpCollector->terminate($req1, $res1);

        // After Request 1 termination, context must be reset completely
        $this->assertSame(0, $context->getQueryCount());
        $this->assertNull($context->getCorrelationId());

        // Simulate Request 2 (worker request 2)
        $req2 = Request::create('/product/view', 'GET');
        $httpCollector->start($req2);
        $corrId2 = $idManager->current();

        $this->assertNotEquals($corrId1, $corrId2, 'Subsequent worker requests must receive distinct correlation IDs.');
        $this->assertSame(0, $context->getQueryCount(), 'Query counts must not carry over from previous worker requests.');

        $res2 = new Response('OK', 200);
        $httpCollector->terminate($req2, $res2);
    }

    public function test_package_generated_query_overhead_is_strictly_zero(): void
    {
        $this->enablePackage();
        config([
            'loglens.http.enabled' => true,
            'loglens.database.enabled' => true,
            'loglens.database.strict_mode' => true,
        ]);

        $executedSqls = [];
        DB::listen(function ($query) use (&$executedSqls) {
            $executedSqls[] = $query->sql;
        });

        // Execute request that runs 3 natural business queries
        $response = $this->get('/app/business-action');
        $response->assertStatus(200);

        // Total queries executed should equal EXACTLY 3:
        // 1. insert into "business_records" ...
        // 2. select count(*) as aggregate from "business_records"
        // 3. select * from "business_records" where "id" = ? limit 1
        $this->assertCount(3, $executedSqls);

        // Verify that none of the executed SQLs are LogLens monitoring queries
        foreach ($executedSqls as $sql) {
            $this->assertStringNotContainsString('loglens', strtolower($sql));
            $this->assertStringNotContainsString('metrics', strtolower($sql));
            $this->assertStringNotContainsString('information_schema', strtolower($sql));
            $this->assertStringNotContainsString('show status', strtolower($sql));
        }

        // Monitoring store recorded the queries without adding any extra ones
        /** @var MetricsStoreInterface $store */
        $store = app(MetricsStoreInterface::class);
        $summary = $store->getDatabaseSummary();
        $this->assertSame(3, $summary['total_queries']);
    }
}
