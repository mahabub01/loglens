<?php

namespace MehrabHossain\LogLens\Tests\Feature;

use Illuminate\Support\Facades\Route;
use MehrabHossain\LogLens\Tests\TestCase;

class RouteTest extends TestCase
{
    public function test_named_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('loglens.dashboard'));
        $this->assertTrue(Route::has('loglens.logs.index'));
        $this->assertTrue(Route::has('loglens.errors.index'));
        $this->assertTrue(Route::has('loglens.http.index'));
        $this->assertTrue(Route::has('loglens.database.index'));
        $this->assertTrue(Route::has('loglens.apache.index'));
        $this->assertTrue(Route::has('loglens.server.index'));
        $this->assertTrue(Route::has('loglens.queue.index'));
        $this->assertTrue(Route::has('loglens.settings.index'));
        $this->assertTrue(Route::has('loglens.actions.execute'));
    }

    public function test_route_url_uses_configured_path(): void
    {
        $this->assertSame(url('loglens'), route('loglens.dashboard'));
        $this->assertSame(url('loglens/logs'), route('loglens.logs.index'));
        $this->assertSame(url('loglens/database'), route('loglens.database.index'));
    }
}
