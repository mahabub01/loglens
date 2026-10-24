<?php

namespace MehrabHossain\LogLens\Tests\Unit;

use Illuminate\Support\Facades\Cache;
use MehrabHossain\LogLens\Services\Storage\CacheMetricsStore;
use MehrabHossain\LogLens\Tests\TestCase;

class CacheMetricsStoreTest extends TestCase
{
    public function test_increment_seeds_and_accumulates_counter(): void
    {
        $store = new CacheMetricsStore(prefix: 'loglens:test');

        $this->assertSame(1, $store->increment('counter'));
        $this->assertSame(6, $store->increment('counter', 5));
        $this->assertSame(6, (int) $store->get('counter'));
    }

    public function test_increment_is_shared_across_store_instances(): void
    {
        $store = new CacheMetricsStore(prefix: 'loglens:test', ttl: 120);

        // A second store instance (separate process) increments the same counter.
        $store->increment('shared');
        (new CacheMetricsStore(prefix: 'loglens:test', ttl: 120))->increment('shared', 2);

        $this->assertSame(3, (int) Cache::get('loglens:test:shared'));
    }

    public function test_forget_removes_value_and_history(): void
    {
        $store = new CacheMetricsStore(prefix: 'loglens:test');

        $store->increment('runs');
        $store->pushHistory('runs', ['status' => 'success']);

        $store->forget('runs');

        $this->assertNull($store->get('runs'));
        $this->assertEmpty($store->getHistory('runs'));
    }

    public function test_ttl_defaults_to_metrics_config(): void
    {
        config(['loglens.metrics.ttl' => 42]);
        $store = new CacheMetricsStore(prefix: 'loglens:test');

        $this->assertSame(42, (new \ReflectionProperty($store, 'ttl'))->getValue($store));
    }
}
