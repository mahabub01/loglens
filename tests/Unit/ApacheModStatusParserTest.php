<?php

namespace MehrabHossain\LogLens\Tests\Unit;

use InvalidArgumentException;
use MehrabHossain\LogLens\Services\Apache\ApacheModStatusParser;
use MehrabHossain\LogLens\Tests\TestCase;

class ApacheModStatusParserTest extends TestCase
{
    protected ApacheModStatusParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new ApacheModStatusParser();
    }

    public function test_parses_standard_apache_mod_status_response(): void
    {
        $raw = <<<'TXT'
Total Accesses: 10450
Total kBytes: 25600
CPULoad: .05
Uptime: 86400
ReqPerSec: 1.25
BytesPerSec: 303.4
BytesPerReq: 250.7
BusyWorkers: 3
IdleWorkers: 97
Scoreboard: _W_K____R................................................
ServerVersion: Apache/2.4.58 (Ubuntu)
TXT;

        $parsed = $this->parser->parse($raw);

        $this->assertSame('Apache/2.4.58 (Ubuntu)', $parsed['server_version']);
        $this->assertSame(86400, $parsed['uptime_seconds']);
        $this->assertSame(10450, $parsed['total_accesses']);
        $this->assertSame(25600 * 1024, $parsed['total_bytes']);
        $this->assertSame(1.25, $parsed['server_req_per_sec']);
        $this->assertSame(303.4, $parsed['server_bytes_per_sec']);
        $this->assertSame(3, $parsed['busy_workers']);
        $this->assertSame(97, $parsed['idle_workers']);
        $this->assertSame('_W_K____R................................................', $parsed['scoreboard_raw']);

        $sb = $parsed['scoreboard_breakdown'];
        $this->assertSame(1, $sb['writing']);   // 'W'
        $this->assertSame(1, $sb['reading']);   // 'R'
        $this->assertSame(1, $sb['keepalive']); // 'K'
        $this->assertSame(6, $sb['waiting']);   // '_'
        $this->assertSame(48, $sb['open_slot']);// '.'
    }

    public function test_parses_alternate_version_format_with_total_bytes(): void
    {
        $raw = <<<'TXT'
Total Accesses: 500
Total Bytes: 5242880
Uptime: 3600
BusyServers: 2
IdleServers: 8
Scoreboard: __W_R.....
Server Version: Apache/2.2.34
TXT;

        $parsed = $this->parser->parse($raw);

        $this->assertSame(500, $parsed['total_accesses']);
        $this->assertSame(5242880, $parsed['total_bytes']);
        $this->assertSame(2, $parsed['busy_workers']);
        $this->assertSame(8, $parsed['idle_workers']);
        $this->assertSame('Apache/2.2.34', $parsed['server_version']);
    }

    public function test_infers_busy_and_idle_workers_from_scoreboard_if_omitted(): void
    {
        $raw = <<<'TXT'
Total Accesses: 100
Uptime: 500
Scoreboard: _W_R.
TXT;

        $parsed = $this->parser->parse($raw);

        // Scoreboard: 1 'W', 1 'R', 2 '_', 1 '.' -> busy = 2 ('W' + 'R'), idle = 2 ('_')
        $this->assertSame(2, $parsed['busy_workers']);
        $this->assertSame(2, $parsed['idle_workers']);
    }

    public function test_throws_exception_on_empty_content(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Empty response received from Apache mod_status');

        $this->parser->parse('');
    }

    public function test_throws_exception_on_html_response(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Received HTML instead of machine-readable');

        $html = '<!DOCTYPE html><html><body><h1>404 Not Found</h1></body></html>';
        $this->parser->parse($html);
    }

    public function test_throws_exception_on_missing_essential_fields(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Response does not contain valid Apache mod_status fields');

        $this->parser->parse("SomeRandomKey: Value\nAnotherKey: 123");
    }
}
