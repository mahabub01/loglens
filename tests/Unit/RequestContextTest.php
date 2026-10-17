<?php

namespace MehrabHossain\LogLens\Tests\Unit;

use MehrabHossain\LogLens\Support\RequestContext;
use MehrabHossain\LogLens\Tests\TestCase;

class RequestContextTest extends TestCase
{
    protected RequestContext $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->context = new RequestContext();
    }

    public function test_initializes_and_tracks_request_lifecycle(): void
    {
        $this->context->start('corr-123', 'api.users.index');

        $this->assertSame('corr-123', $this->context->getCorrelationId());
        $this->assertSame('api.users.index', $this->context->getRoute());
        $this->assertSame(0, $this->context->getQueryCount());
        $this->assertSame(0.0, $this->context->getQueryDurationMs());
        $this->assertSame(0, $this->context->getSlowQueryCount());
    }

    public function test_records_queries_and_durations(): void
    {
        $this->context->start('corr-123', 'api.users.index');

        $this->context->recordQuery('fp-1', 12.5, false, 'SELECT * FROM users WHERE id = ?');
        $this->context->recordQuery('fp-2', 550.0, true, 'SELECT * FROM orders WHERE user_id = ?');

        $this->assertSame(2, $this->context->getQueryCount());
        $this->assertEquals(562.5, $this->context->getQueryDurationMs());
        $this->assertSame(1, $this->context->getSlowQueryCount());
    }

    public function test_detects_potential_n_plus_one_queries(): void
    {
        $this->context->start('corr-456', 'users.show');

        $fingerprint = 'fp-user-profile';
        $sql = 'SELECT * FROM user_profiles WHERE user_id = ?';

        // Execute twice (below threshold 3)
        $this->context->recordQuery($fingerprint, 2.0, false, $sql);
        $this->context->recordQuery($fingerprint, 2.5, false, $sql);

        $alerts = $this->context->getPotentialNPlusOneAlerts(3);
        $this->assertEmpty($alerts, 'Under threshold queries should not generate N+1 alerts.');

        // Execute 3rd and 4th times (meeting and exceeding threshold)
        $this->context->recordQuery($fingerprint, 3.0, false, $sql);
        $this->context->recordQuery($fingerprint, 2.2, false, $sql);

        $alerts = $this->context->getPotentialNPlusOneAlerts(3);
        $this->assertCount(1, $alerts);
        $this->assertSame(4, $alerts[0]['count']);
        $this->assertSame($fingerprint, $alerts[0]['fingerprint']);
        $this->assertStringContainsString('Consider eager loading', $alerts[0]['hint']);
        $this->assertSame('users.show', $alerts[0]['route']);
    }

    public function test_summary_returns_aggregated_metrics(): void
    {
        $this->context->start('corr-789', 'checkout.process');
        $this->context->recordQuery('fp-cart', 10.0, false, 'SELECT * FROM carts WHERE id = ?');
        $this->context->recordQuery('fp-item', 20.0, false, 'SELECT * FROM items WHERE id = ?');

        $summary = $this->context->summary();

        $this->assertSame('corr-789', $summary['correlation_id']);
        $this->assertSame('checkout.process', $summary['route']);
        $this->assertSame(2, $summary['query_count']);
        $this->assertEquals(30.0, $summary['query_duration_ms']);
        $this->assertSame(0, $summary['slow_query_count']);
        $this->assertIsArray($summary['n_plus_one_alerts']);
    }

    public function test_reset_clears_all_request_state(): void
    {
        $this->context->start('corr-999', 'test.route');
        $this->context->recordQuery('fp-1', 15.0, true, 'SELECT * FROM posts');

        $this->assertSame(1, $this->context->getQueryCount());

        $this->context->reset();

        $this->assertNull($this->context->getCorrelationId());
        $this->assertNull($this->context->getRoute());
        $this->assertSame(0, $this->context->getQueryCount());
        $this->assertSame(0.0, $this->context->getQueryDurationMs());
        $this->assertSame(0, $this->context->getSlowQueryCount());
        $this->assertEmpty($this->context->getPotentialNPlusOneAlerts(1));
    }
}
