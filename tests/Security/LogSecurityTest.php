<?php

namespace MehrabHossain\LogLens\Tests\Security;

use Illuminate\Support\Facades\DB;
use MehrabHossain\LogLens\Contracts\LogFileManagerInterface;
use MehrabHossain\LogLens\Tests\TestCase;

class LogSecurityTest extends TestCase
{
    protected LogFileManagerInterface $fileManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enablePackage();
        $this->authorizeAccess();
        $this->fileManager = app(LogFileManagerInterface::class);
    }

    public function test_blocks_path_traversal_attempts(): void
    {
        $maliciousIdentifiers = [
            '../../etc/passwd',
            '..%2F..%2Fetc%2Fpasswd',
            '../storage/logs/laravel.log',
            '....//....//....//',
            '../../.env',
        ];

        foreach ($maliciousIdentifiers as $badId) {
            $response = $this->get('/loglens/logs/' . urlencode($badId) . '/download');
            // Should be 403, 404, or 500 security exception (never 200 with file content)
            $this->assertNotSame(200, $response->getStatusCode());
        }
    }

    public function test_escapes_xss_payloads_in_log_messages(): void
    {
        $xssPayload = '<script>alert("XSS_ATTACK_VECTOR")</script><img src="x" onerror="alert(1)">';
        $logContent = "[2026-10-17 12:00:00] local.ERROR: {$xssPayload}\n";

        $this->createLogFile('xss.log', $logContent);

        $response = $this->get('/loglens/logs');

        $response->assertStatus(200);

        // Blade must HTML escape the content, never rendering unescaped script tags
        $response->assertDontSee('<script>alert("XSS_ATTACK_VECTOR")</script>', false);
        $response->assertSee('&lt;script&gt;alert(&quot;XSS_ATTACK_VECTOR&quot;)&lt;/script&gt;', false);
    }

    public function test_log_operations_execute_zero_database_queries(): void
    {
        $this->createLogFile('db_check.log', "[2026-10-17 12:00:00] local.ERROR: Error event\n");

        $executedQueries = [];
        DB::listen(function ($query) use (&$executedQueries) {
            $executedQueries[] = $query->sql;
        });

        // 1. View logs list & entries
        $this->get('/loglens/logs')->assertStatus(200);

        // 2. View error analysis
        $this->get('/loglens/errors')->assertStatus(200);

        $files = $this->fileManager->getLogFiles();
        $file = reset($files);
        $this->getJson("/loglens/api/logs/tail?file={$file->identifier}&offset=0")->assertStatus(200);

        // Assert strictly ZERO queries executed
        $this->assertCount(
            0,
            $executedQueries,
            'Log browsing, parsing, error grouping and live tail must NOT execute any database queries.'
        );
    }
}
