<?php

namespace MehrabHossain\LogLens\Tests\Unit;

use MehrabHossain\LogLens\Services\Apache\CacheApacheMetricsStore;
use MehrabHossain\LogLens\Tests\TestCase;

class CacheApacheMetricsStoreTest extends TestCase
{
    protected CacheApacheMetricsStore $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->store = new CacheApacheMetricsStore(
            prefix: 'test:apache',
            historyLimit: 5,
            ttlSeconds: 300
        );
        $this->store->clear();
    }

    protected function tearDown(): void
    {
        $this->store->clear();
        parent::tearDown();
    }

    public function test_stores_latest_and_retrieves_snapshot(): void
    {
        $this->assertNull($this->store->getLatest());

        $snapshot = [
            'timestamp' => microtime(true),
            'busy_workers' => 4,
            'idle_workers' => 96,
            'rps' => 12.5,
        ];

        $this->store->record($snapshot);

        $latest = $this->store->getLatest();
        $this->assertNotNull($latest);
        $this->assertSame(4, $latest['busy_workers']);
        $this->assertEquals(12.5, $latest['rps']);
    }

    public function test_appends_to_history_and_bounds_limit(): void
    {
        // Limit is 5
        for ($i = 1; $i <= 8; $i++) {
            $this->store->record([
                'timestamp' => microtime(true),
                'seq' => $i,
                'busy_workers' => $i,
            ]);
        }

        $history = $this->store->getHistory(10);

        // Max 5 entries retained
        $this->assertCount(5, $history);
        // Oldest retained is seq 4
        $this->assertSame(4, $history[0]['seq']);
        // Newest retained is seq 8
        $this->assertSame(8, end($history)['seq']);
    }

    public function test_detects_stale_metrics(): void
    {
        // 1. When empty, considered stale
        $this->assertTrue($this->store->isStale(5));

        // 2. When recorded 10 seconds ago, considered stale at 5s threshold
        $oldSnapshot = [
            'timestamp' => microtime(true) - 10,
            'busy_workers' => 2,
        ];
        $this->store->record($oldSnapshot);
        $this->assertTrue($this->store->isStale(5));

        // 3. When recorded 1 second ago, fresh
        $freshSnapshot = [
            'timestamp' => microtime(true) - 1,
            'busy_workers' => 2,
        ];
        $this->store->record($freshSnapshot);
        $this->assertFalse($this->store->isStale(5));
    }

    public function test_records_and_clears_errors(): void
    {
        $this->assertNull($this->store->getLastError());

        $this->store->recordError('Connection refused to 127.0.0.1:80');
        $this->assertSame('Connection refused to 127.0.0.1:80', $this->store->getLastError());

        // Successful recording clears error
        $this->store->record(['timestamp' => microtime(true), 'busy_workers' => 1]);
        $this->assertNull($this->store->getLastError());
    }

    public function test_clear_wipes_all_metrics(): void
    {
        $this->store->record(['timestamp' => microtime(true)]);
        $this->store->recordError('Error');

        $this->store->clear();

        $this->assertNull($this->store->getLatest());
        $this->assertEmpty($this->store->getHistory());
        $this->assertNull($this->store->getLastError());
    }
}
