<?php

namespace MehrabHossain\LogLens\Tests\Unit;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use MehrabHossain\LogLens\Support\CorrelationIdManager;
use MehrabHossain\LogLens\Tests\TestCase;
use Symfony\Component\HttpFoundation\Response;

class CorrelationIdManagerTest extends TestCase
{
    protected CorrelationIdManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new CorrelationIdManager();
    }

    public function test_generates_uuid_when_no_header_is_present(): void
    {
        $request = Request::create('/api/test', 'GET');
        $id = $this->manager->resolve($request);

        $this->assertNotEmpty($id);
        $this->assertTrue(Str::isUuid($id), 'Generated correlation ID should be a valid UUID');
    }

    public function test_accepts_valid_incoming_x_correlation_id(): void
    {
        $customId = 'req-prod-us-east-1-987654321';
        $request = Request::create('/api/test', 'GET');
        $request->headers->set('X-Correlation-ID', $customId);

        $resolved = $this->manager->resolve($request);

        $this->assertSame($customId, $resolved);
        $this->assertSame($customId, $this->manager->current());
    }

    public function test_accepts_valid_incoming_x_request_id(): void
    {
        $customId = '550e8400-e29b-41d4-a716-446655440000';
        $request = Request::create('/api/test', 'GET');
        $request->headers->set('X-Request-ID', $customId);

        $resolved = $this->manager->resolve($request);

        $this->assertSame($customId, $resolved);
    }

    public function test_rejects_malformed_incoming_ids(): void
    {
        // 1. Too short (< 4 chars)
        $shortReq = Request::create('/api/test', 'GET');
        $shortReq->headers->set('X-Correlation-ID', 'ab');
        $resolvedShort = $this->manager->resolve($shortReq);
        $this->assertNotEquals('ab', $resolvedShort);
        $this->assertTrue(Str::isUuid($resolvedShort));

        // 2. Too long (> 64 chars)
        $longReq = Request::create('/api/test', 'GET');
        $longReq->headers->set('X-Correlation-ID', str_repeat('a', 65));
        $resolvedLong = $this->manager->resolve($longReq);
        $this->assertNotEquals(str_repeat('a', 65), $resolvedLong);
        $this->assertTrue(Str::isUuid($resolvedLong));

        // 3. Header injection attempt (newlines, control characters)
        $injectionReq = Request::create('/api/test', 'GET');
        $injectionReq->headers->set('X-Correlation-ID', "valid-id\r\nX-Injected-Header: evil");
        $resolvedInjection = $this->manager->resolve($injectionReq);
        $this->assertStringNotContainsString('evil', $resolvedInjection);
        $this->assertTrue(Str::isUuid($resolvedInjection));
    }

    public function test_attaches_correlation_id_to_response(): void
    {
        $request = Request::create('/api/test', 'GET');
        $id = $this->manager->resolve($request);

        $response = new Response('OK', 200);
        $this->manager->attachToResponse($response);

        $this->assertTrue($response->headers->has('X-Correlation-ID'));
        $this->assertSame($id, $response->headers->get('X-Correlation-ID'));
    }

    public function test_reset_clears_active_correlation_id(): void
    {
        $request = Request::create('/api/test', 'GET');
        $id1 = $this->manager->resolve($request);

        $this->assertSame($id1, $this->manager->current());

        $this->manager->reset();

        // After reset, calling current() generates a fresh ID
        $id2 = $this->manager->current();
        $this->assertNotSame($id1, $id2);
    }
}
