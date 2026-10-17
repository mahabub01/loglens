<?php

namespace MehrabHossain\LogLens\Tests\Feature;

use MehrabHossain\LogLens\Contracts\LogFileManagerInterface;
use MehrabHossain\LogLens\Tests\TestCase;

class LogViewerTest extends TestCase
{
    protected LogFileManagerInterface $fileManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enablePackage();
        $this->fileManager = app(LogFileManagerInterface::class);
    }

    public function test_unauthorized_user_cannot_access_log_viewer(): void
    {
        $response = $this->get('/loglens/logs');

        $response->assertStatus(403);
    }

    public function test_authorized_user_can_view_logs(): void
    {
        $this->authorizeAccess();

        $this->createLogFile('laravel.log', "[2026-10-17 12:00:00] local.INFO: Application booted successfully\n");

        $response = $this->get('/loglens/logs');

        $response->assertStatus(200);
        $response->assertViewIs('loglens::logs.index');
        $response->assertSee('laravel.log');
        $response->assertSee('Application booted successfully');
    }

    public function test_filters_by_severity_level(): void
    {
        $this->authorizeAccess();

        $content = <<<'LOG'
[2026-10-17 12:00:00] local.INFO: System regular info event
[2026-10-17 12:05:00] local.ERROR: Fatal database outage event
LOG;

        $this->createLogFile('laravel.log', $content);
        $files = $this->fileManager->getLogFiles();
        $file = reset($files);

        $response = $this->get('/loglens/logs?file=' . $file->identifier . '&level=ERROR');

        $response->assertStatus(200);
        $response->assertSee('Fatal database outage event');
        $response->assertDontSee('System regular info event');
    }

    public function test_filters_by_keyword_search(): void
    {
        $this->authorizeAccess();

        $content = <<<'LOG'
[2026-10-17 12:00:00] local.INFO: Payment processing initiated
[2026-10-17 12:05:00] local.INFO: User registration completed
LOG;

        $this->createLogFile('laravel.log', $content);
        $files = $this->fileManager->getLogFiles();
        $file = reset($files);

        $response = $this->get('/loglens/logs?file=' . $file->identifier . '&q=Payment');

        $response->assertStatus(200);
        $response->assertSee('Payment processing initiated');
        $response->assertDontSee('User registration completed');
    }

    public function test_secure_log_download(): void
    {
        $this->authorizeAccess();

        $this->createLogFile('app_download.log', "[2026-10-17 10:00:00] local.INFO: Download test content\n");
        $files = $this->fileManager->getLogFiles();
        $file = reset($files);

        $response = $this->get('/loglens/logs/' . $file->identifier . '/download');

        $response->assertStatus(200);
        $response->assertHeader('content-disposition', 'attachment; filename=app_download.log');
    }
}
