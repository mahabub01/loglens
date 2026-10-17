<?php

namespace MehrabHossain\LogLens\Tests\Unit;

use DateTimeImmutable;
use MehrabHossain\LogLens\Services\ErrorFingerprinter;
use MehrabHossain\LogLens\Tests\TestCase;
use MehrabHossain\LogLens\ValueObjects\LogEntry;

class ErrorFingerprinterTest extends TestCase
{
    protected ErrorFingerprinter $fingerprinter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fingerprinter = new ErrorFingerprinter();
    }

    public function test_normalizes_dynamic_elements(): void
    {
        $raw = 'Failed processing order id=18492 with uuid: e4eaaaf2-d142-11e1-b3e4-080027620cdd at 2026-10-17 14:00:00 in /app/Order.php:89';

        $normalized = $this->fingerprinter->normalizeMessage($raw);

        $this->assertStringNotContainsString('18492', $normalized);
        $this->assertStringNotContainsString('e4eaaaf2-d142-11e1-b3e4-080027620cdd', $normalized);
        $this->assertStringNotContainsString('2026-10-17 14:00:00', $normalized);
        $this->assertStringContainsString('[UUID]', $normalized);
        $this->assertStringContainsString('[DATETIME]', $normalized);
        $this->assertStringContainsString(':[LINE]', $normalized);
    }

    public function test_identical_errors_with_different_ids_produce_same_fingerprint(): void
    {
        $entryA = new LogEntry(
            id: '1',
            timestamp: new DateTimeImmutable('2026-10-17 10:00:00'),
            channel: 'production',
            severity: 'ERROR',
            message: 'ModelNotFoundException: No query results for model [App\Models\Order] 45847 in /app/OrderController.php:123',
            exceptionClass: 'ModelNotFoundException'
        );

        $entryB = new LogEntry(
            id: '2',
            timestamp: new DateTimeImmutable('2026-10-17 11:30:00'),
            channel: 'production',
            severity: 'ERROR',
            message: 'ModelNotFoundException: No query results for model [App\Models\Order] 99281 in /app/OrderController.php:123',
            exceptionClass: 'ModelNotFoundException'
        );

        $fpA = $this->fingerprinter->fingerprint($entryA);
        $fpB = $this->fingerprinter->fingerprint($entryB);

        $this->assertSame($fpA, $fpB, 'Repeated errors with dynamic record IDs must produce identical fingerprints.');
    }

    public function test_different_errors_produce_different_fingerprints(): void
    {
        $entryA = new LogEntry(
            id: '1',
            timestamp: new DateTimeImmutable(),
            channel: 'production',
            severity: 'ERROR',
            message: 'PaymentGatewayException: Connection timeout to Stripe API',
            exceptionClass: 'PaymentGatewayException'
        );

        $entryB = new LogEntry(
            id: '2',
            timestamp: new DateTimeImmutable(),
            channel: 'production',
            severity: 'ERROR',
            message: 'RedisException: Could not connect to Redis host 127.0.0.1:6379',
            exceptionClass: 'RedisException'
        );

        $fpA = $this->fingerprinter->fingerprint($entryA);
        $fpB = $this->fingerprinter->fingerprint($entryB);

        $this->assertNotSame($fpA, $fpB);
    }
}
