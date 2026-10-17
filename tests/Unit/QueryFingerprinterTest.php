<?php

namespace MehrabHossain\LogLens\Tests\Unit;

use MehrabHossain\LogLens\Support\QueryFingerprinter;
use MehrabHossain\LogLens\Tests\TestCase;

class QueryFingerprinterTest extends TestCase
{
    protected QueryFingerprinter $fingerprinter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fingerprinter = new QueryFingerprinter();
    }

    public function test_normalizes_numeric_literals(): void
    {
        $sql = 'SELECT * FROM users WHERE id = 42 AND age >= 21 AND balance > 100.50';
        $normalized = $this->fingerprinter->normalize($sql);

        $this->assertEquals('SELECT * FROM users WHERE id = ? AND age >= ? AND balance > ?', $normalized);
    }

    public function test_normalizes_string_literals(): void
    {
        $sql = "SELECT * FROM users WHERE email = 'john.doe@example.com' AND status = 'active'";
        $normalized = $this->fingerprinter->normalize($sql);

        $this->assertEquals('SELECT * FROM users WHERE email = ? AND status = ?', $normalized);
    }

    public function test_normalizes_uuids(): void
    {
        $sql = 'SELECT * FROM accounts WHERE uuid = 123e4567-e89b-12d3-a456-426614174000';
        $normalized = $this->fingerprinter->normalize($sql);

        $this->assertEquals('SELECT * FROM accounts WHERE uuid = ?', $normalized);
    }

    public function test_normalizes_in_lists(): void
    {
        $sql = "SELECT * FROM orders WHERE status IN ('pending', 'processing', 'completed')";
        $normalized = $this->fingerprinter->normalize($sql);

        $this->assertEquals('SELECT * FROM orders WHERE status IN (?)', $normalized);

        $sqlNumbers = 'SELECT * FROM items WHERE id IN (1, 2, 3, 4, 5)';
        $normalizedNumbers = $this->fingerprinter->normalize($sqlNumbers);

        $this->assertEquals('SELECT * FROM items WHERE id IN (?)', $normalizedNumbers);
    }

    public function test_normalizes_booleans(): void
    {
        $sql = 'SELECT * FROM posts WHERE is_published = true AND is_archived = false';
        $normalized = $this->fingerprinter->normalize($sql);

        $this->assertEquals('SELECT * FROM posts WHERE is_published = ? AND is_archived = ?', $normalized);
    }

    public function test_collapses_whitespace(): void
    {
        $sql = "SELECT   *\n\tFROM users\n   WHERE id = 1";
        $normalized = $this->fingerprinter->normalize($sql);

        $this->assertEquals('SELECT * FROM users WHERE id = ?', $normalized);
    }

    public function test_fingerprint_is_deterministic_16_char_hash(): void
    {
        $sql1 = 'SELECT * FROM users WHERE id = 10';
        $sql2 = 'SELECT * FROM users WHERE id = 999';

        $fp1 = $this->fingerprinter->fingerprint($sql1);
        $fp2 = $this->fingerprinter->fingerprint($sql2);

        $this->assertSame(16, strlen($fp1));
        $this->assertSame($fp1, $fp2, 'Equivalent queries with different parameters must produce identical fingerprints.');
    }

    public function test_redacts_sensitive_parameters(): void
    {
        config(['loglens.security.sensitive_keys' => ['password', 'secret', 'token']]);

        $sql = "UPDATE users SET password = 'plaintext_password_123', token = 'secret_bearer_token'";
        $redacted = $this->fingerprinter->redact($sql);

        $this->assertStringNotContainsString('plaintext_password_123', $redacted);
        $this->assertStringNotContainsString('secret_bearer_token', $redacted);
        $this->assertStringContainsString('[REDACTED]', $redacted);
    }
}
