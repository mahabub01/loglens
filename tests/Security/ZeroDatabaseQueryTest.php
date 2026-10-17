<?php

namespace MehrabHossain\LogLens\Tests\Security;

use Illuminate\Support\Facades\DB;
use MehrabHossain\LogLens\Tests\TestCase;

class ZeroDatabaseQueryTest extends TestCase
{
    public function test_dashboard_renders_with_zero_database_queries(): void
    {
        $this->enablePackage();
        $this->authorizeAccess();

        // Enable query log on default connection
        DB::connection()->enableQueryLog();

        $executedQueries = [];
        DB::listen(function ($query) use (&$executedQueries) {
            $executedQueries[] = $query->sql;
        });

        // Request the dashboard
        $response = $this->get('/loglens');

        $response->assertStatus(200);

        // Verify that 0 queries were executed
        $this->assertCount(
            0,
            $executedQueries,
            'LogLens dashboard must not execute any database queries in strict monitoring mode.'
        );

        $this->assertEmpty(
            DB::connection()->getQueryLog(),
            'Query log should be empty when loading the LogLens dashboard.'
        );
    }

    public function test_section_routes_render_with_zero_database_queries(): void
    {
        $this->enablePackage();
        $this->authorizeAccess();

        $routesToTest = [
            '/loglens/logs',
            '/loglens/errors',
            '/loglens/http',
            '/loglens/database',
            '/loglens/apache',
            '/loglens/server-health',
            '/loglens/queue',
            '/loglens/scheduler',
            '/loglens/settings',
        ];

        foreach ($routesToTest as $uri) {
            $executedQueries = [];
            DB::listen(function ($query) use (&$executedQueries) {
                $executedQueries[] = $query->sql;
            });

            $response = $this->get($uri);
            $response->assertStatus(200);

            $this->assertCount(
                0,
                $executedQueries,
                "Route {$uri} must not execute any database queries."
            );
        }
    }
}
