<?php

namespace MehrabHossain\LogLens\Tests\Unit;

use DateTimeImmutable;
use MehrabHossain\LogLens\Contracts\LogAnalyzerInterface;
use MehrabHossain\LogLens\Tests\TestCase;
use MehrabHossain\LogLens\ValueObjects\LogEntry;

class LogAnalyzerTest extends TestCase
{
    protected LogAnalyzerInterface $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer = app(LogAnalyzerInterface::class);
    }

    public function test_analyzes_and_groups_repeated_errors(): void
    {
        $entries = [
            new LogEntry(
                id: '1',
                timestamp: new DateTimeImmutable('2026-10-17 08:00:00'),
                channel: 'production',
                severity: 'INFO',
                message: 'Application booted'
            ),
            new LogEntry(
                id: '2',
                timestamp: new DateTimeImmutable('2026-10-17 08:30:00'),
                channel: 'production',
                severity: 'ERROR',
                message: 'Database connection failed for user #101',
                exceptionClass: 'QueryException'
            ),
            new LogEntry(
                id: '3',
                timestamp: new DateTimeImmutable('2026-10-17 09:15:00'),
                channel: 'production',
                severity: 'ERROR',
                message: 'Database connection failed for user #202',
                exceptionClass: 'QueryException'
            ),
            new LogEntry(
                id: '4',
                timestamp: new DateTimeImmutable('2026-10-17 10:00:00'),
                channel: 'production',
                severity: 'ERROR',
                message: 'Payment rejected by gateway',
                exceptionClass: 'PaymentException'
            ),
        ];

        $analysis = $this->analyzer->analyze($entries);

        $this->assertSame(4, $analysis['total_entries']);
        $this->assertSame(3, $analysis['error_count']);
        $this->assertSame(1, $analysis['info_count']);
        $this->assertSame(2, $analysis['unique_error_groups']);

        // Group 1 should be the QueryException with count = 2
        $topGroup = $analysis['groups'][0];
        $this->assertSame('QueryException', $topGroup->exceptionClass);
        $this->assertSame(2, $topGroup->count);
        $this->assertSame('2026-10-17 08:30:00', $topGroup->firstSeen->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-17 09:15:00', $topGroup->lastSeen->format('Y-m-d H:i:s'));

        // Chart integrations
        $this->assertNotEmpty($analysis['top_errors']);
        $this->assertArrayHasKey('labels', $analysis['timeline']);
        $this->assertArrayHasKey('counts', $analysis['timeline']);
    }
}
