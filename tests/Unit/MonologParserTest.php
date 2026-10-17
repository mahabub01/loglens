<?php

namespace MehrabHossain\LogLens\Tests\Unit;

use MehrabHossain\LogLens\Contracts\LogParserInterface;
use MehrabHossain\LogLens\Tests\TestCase;

class MonologParserTest extends TestCase
{
    protected LogParserInterface $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = app(LogParserInterface::class);
    }

    public function test_parses_standard_laravel_monolog_entry(): void
    {
        $logText = '[2026-10-17 14:15:20] production.ERROR: Payment gateway timeout {"gateway":"stripe","amount":99.95}';

        $entries = $this->parser->parseContent($logText);

        $this->assertCount(1, $entries);
        $entry = $entries[0];

        $this->assertSame('production', $entry->channel);
        $this->assertSame('ERROR', $entry->severity);
        $this->assertSame('Payment gateway timeout', $entry->message);
        $this->assertSame('2026-10-17 14:15:20', $entry->timestamp->format('Y-m-d H:i:s'));
        $this->assertIsArray($entry->context);
        $this->assertSame('stripe', $entry->context['gateway']);
        $this->assertEquals(99.95, $entry->context['amount']);
        $this->assertTrue($entry->isError());
    }

    public function test_parses_multiline_exception_with_stack_trace(): void
    {
        $rawLog = <<<'LOG'
[2026-10-17 15:00:00] local.CRITICAL: Illuminate\Database\QueryException: SQLSTATE[HY000]: General error: 2006 MySQL server has gone away in /var/www/vendor/laravel/framework/src/Illuminate/Database/Connection.php:795
[stacktrace]
#0 /var/www/vendor/laravel/framework/src/Illuminate/Database/Connection.php(795): PDOStatement->execute()
#1 /var/www/app/Http/Controllers/OrderController.php(42): Illuminate\Database\Connection->select()
#2 /var/www/public/index.php(52): App\Http\Controllers\OrderController->checkout()
#3 {main}
LOG;

        $entries = $this->parser->parseContent($rawLog);

        $this->assertCount(1, $entries);
        $entry = $entries[0];

        $this->assertSame('CRITICAL', $entry->severity);
        $this->assertSame('Illuminate\Database\QueryException', $entry->exceptionClass);
        $this->assertNotNull($entry->stackTrace);
        $this->assertStringContainsString('#0 /var/www/vendor/laravel', $entry->stackTrace);
        $this->assertStringContainsString('#1 /var/www/app/Http/Controllers/OrderController.php', $entry->stackTrace);
    }

    public function test_parses_all_standard_severity_levels(): void
    {
        $severities = ['EMERGENCY', 'ALERT', 'CRITICAL', 'ERROR', 'WARNING', 'NOTICE', 'INFO', 'DEBUG'];
        $content = '';

        foreach ($severities as $i => $sev) {
            $content .= "[2026-10-17 10:00:0{$i}] local.{$sev}: Test event {$sev}\n";
        }

        $entries = $this->parser->parseContent($content);

        $this->assertCount(8, $entries);
        foreach ($entries as $i => $entry) {
            $this->assertSame($severities[$i], $entry->severity);
        }
    }

    public function test_redacts_sensitive_parameters_from_message_and_context(): void
    {
        config(['loglens.security.redact_sensitive_data' => true]);

        $logText = '[2026-10-17 11:22:33] local.INFO: User login with password: secret123 and token="bearer_abc_xyz" {"password":"superSecretPassword","token":"secretToken","user":"john"}';

        $entries = $this->parser->parseContent($logText);

        $this->assertCount(1, $entries);
        $entry = $entries[0];

        $this->assertStringNotContainsString('superSecretPassword', json_encode($entry->context));
        $this->assertStringNotContainsString('secretToken', json_encode($entry->context));
        $this->assertSame('[REDACTED]', $entry->context['password']);
        $this->assertSame('[REDACTED]', $entry->context['token']);
        $this->assertSame('john', $entry->context['user']);
    }

    public function test_handles_malformed_log_entries_gracefully(): void
    {
        $malformedText = "Random unexpected log output without brackets or standard Monolog headers";

        $entries = $this->parser->parseContent($malformedText);

        $this->assertCount(1, $entries);
        $entry = $entries[0];

        $this->assertSame('raw', $entry->channel);
        $this->assertSame($malformedText, $entry->message);
    }
}
