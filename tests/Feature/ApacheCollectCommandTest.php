<?php

namespace MehrabHossain\LogLens\Tests\Feature;

use Illuminate\Support\Facades\Http;
use MehrabHossain\LogLens\Contracts\ApacheMetricsStoreInterface;
use MehrabHossain\LogLens\Tests\TestCase;

class ApacheCollectCommandTest extends TestCase
{
    protected ApacheMetricsStoreInterface $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enablePackage();
        config(['loglens.apache.enabled' => true]);

        $this->store = app(ApacheMetricsStoreInterface::class);
        $this->store->clear();
    }

    protected function tearDown(): void
    {
        $this->store->clear();
        parent::tearDown();
    }

    public function test_artisan_command_executes_single_iteration(): void
    {
        $mockOutput = <<<'TXT'
Total Accesses: 1000
Total kBytes: 20480
Uptime: 3600
BusyWorkers: 2
IdleWorkers: 98
Scoreboard: _W_K....................................................
ServerVersion: Apache/2.4.58
TXT;

        Http::fake([
            'http://127.0.0.1/server-status?auto' => Http::response($mockOutput, 200),
        ]);

        $this->artisan('loglens:apache-collect', ['--once' => true])
            ->expectsOutputToContain('Starting LogLens Apache mod_status collector')
            ->expectsOutputToContain('Busy: 2, Idle: 98')
            ->assertExitCode(0);

        $latest = $this->store->getLatest();
        $this->assertNotNull($latest);
        $this->assertSame(2, $latest['busy_workers']);
        $this->assertSame(98, $latest['idle_workers']);
    }

    public function test_artisan_command_handles_collection_errors_gracefully(): void
    {
        Http::fake([
            'http://127.0.0.1/server-status?auto' => Http::response('Server Error', 500),
        ]);

        $this->artisan('loglens:apache-collect', ['--once' => true])
            ->expectsOutputToContain('ERROR:')
            ->assertExitCode(1);
    }

    public function test_artisan_command_executes_multiple_iterations(): void
    {
        $mockOutput = <<<'TXT'
Total Accesses: 1000
Total kBytes: 20480
Uptime: 3600
BusyWorkers: 3
IdleWorkers: 97
Scoreboard: _WWK....................................................
ServerVersion: Apache/2.4.58
TXT;

        Http::fake([
            'http://127.0.0.1/server-status?auto' => Http::response($mockOutput, 200),
        ]);

        $this->artisan('loglens:apache-collect', [
            '--iterations' => 2,
            '--interval' => 0.05,
        ])
            ->expectsOutputToContain('Execution limit: 2 iteration(s)')
            ->expectsOutputToContain('Apache collector stopped after 2 iteration(s)')
            ->assertExitCode(0);

        $history = $this->store->getHistory(10);
        $this->assertCount(2, $history);
    }
}
