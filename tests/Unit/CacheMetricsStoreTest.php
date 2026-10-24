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

    public function test_member_set_tracks_prunes_and_removes_members(): void
    {
        $store = new CacheMetricsStore(prefix: 'loglens:test');

        $store->touchMember('workers', 'web01:100', 60);
        (new CacheMetricsStore(prefix: 'loglens:test'))->touchMember('workers', 'web01:101', 60);

        $members = $store->getMembers('workers', 60);
        $this->assertSame(['web01:100', 'web01:101'], array_keys($members));
        $this->assertIsFloat($members['web01:100']);

        // A member last seen 2 minutes ago falls outside a 60s window
        Cache::put('loglens:test:set:workers', ['web01:100' => microtime(true), 'web01:102' => microtime(true) - 120], 600);
        $this->assertSame(['web01:100'], array_keys($store->getMembers('workers', 60)));

        $store->removeMember('workers', 'web01:100');
        $this->assertSame([], $store->getMembers('workers', 60));
    }

    public function test_ttl_defaults_to_metrics_config(): void
    {
        config(['loglens.metrics.ttl' => 42]);
        $store = new CacheMetricsStore(prefix: 'loglens:test');

        $this->assertSame(42, (new \ReflectionProperty($store, 'ttl'))->getValue($store));
    }
}
