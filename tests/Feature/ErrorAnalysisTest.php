<?php

namespace MehrabHossain\LogLens\Tests\Feature;

use MehrabHossain\LogLens\Tests\TestCase;

class ErrorAnalysisTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->enablePackage();
    }

    public function test_unauthorized_user_cannot_access_error_analysis(): void
    {
        $response = $this->get('/loglens/errors');

        $response->assertStatus(403);
    }

    public function test_authorized_user_can_view_error_analysis_and_grouping(): void
    {
        $this->authorizeAccess();

        $content = <<<'LOG'
[2026-10-17 12:00:00] local.ERROR: RuntimeException: Memory limit exceeded in /app/Service.php:12
[2026-10-17 12:30:00] local.ERROR: RuntimeException: Memory limit exceeded in /app/Service.php:12
[2026-10-17 13:00:00] local.WARNING: Slow external API call
LOG;

        $this->createLogFile('laravel.log', $content);

        $response = $this->get('/loglens/errors');

        $response->assertStatus(200);
        $response->assertViewIs('loglens::errors.index');
        $response->assertSee('Memory limit exceeded');
        $response->assertSee('RuntimeException');
        $response->assertSee('2 &times;', false);
    }
}
