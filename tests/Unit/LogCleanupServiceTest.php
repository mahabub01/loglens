<?php

namespace MehrabHossain\LogLens\Tests\Unit;

use MehrabHossain\LogLens\Contracts\LogCleanupInterface;
use MehrabHossain\LogLens\Contracts\LogFileManagerInterface;
use MehrabHossain\LogLens\Exceptions\LogLensSecurityException;
use MehrabHossain\LogLens\Tests\TestCase;

class LogCleanupServiceTest extends TestCase
{
    protected LogCleanupInterface $cleanup;
    protected LogFileManagerInterface $fileManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanup = app(LogCleanupInterface::class);
        $this->fileManager = app(LogFileManagerInterface::class);
    }

    public function test_preview_retention_identifies_aged_files_without_deleting(): void
    {
        $oldFile = $this->createLogFile('laravel-2026-08-01.log', "Old log entry\n");
        // Set modification time to 60 days ago
        touch($oldFile, time() - (60 * 86400));

        $freshFile = $this->createLogFile('laravel-2026-10-17.log', "Fresh log entry\n");
        touch($freshFile, time());

        $preview = $this->cleanup->previewRetentionCleanup(30);

        $this->assertSame(1, $preview['files_count']);
        $this->assertSame('laravel-2026-08-01.log', $preview['files'][0]['filename']);

        // Verify file still exists on disk
        $this->assertFileExists($oldFile);
    }

    public function test_cleanup_throws_security_exception_if_destructive_config_is_disabled(): void
    {
        config(['loglens.cleanup.allow_destructive_actions' => false]);

        $this->createLogFile('laravel-2026-08-01.log', "Old\n");
        $files = $this->fileManager->getLogFiles();
        $logFile = reset($files);

        $this->expectException(LogLensSecurityException::class);
        $this->cleanup->clearFile($logFile);
    }

    public function test_safe_in_place_truncation_of_log_file(): void
    {
        config(['loglens.cleanup.allow_destructive_actions' => true]);

        $path = $this->createLogFile('laravel.log', "Line 1\nLine 2\nLine 3\n");
        $this->assertGreaterThan(0, filesize($path));

        $files = $this->fileManager->getLogFiles();
        $logFile = reset($files);

        $report = $this->cleanup->clearFile($logFile, archiveBeforeClear: false);

        clearstatcache(true, $path);
        $this->assertSame(0, filesize($path), 'File must be truncated to 0 bytes.');
        $this->assertFileExists($path, 'File handle must remain present in place on disk.');
        $this->assertSame('laravel.log', $report['filename']);
    }

    public function test_execute_retention_cleanup_deletes_aged_files(): void
    {
        config(['loglens.cleanup.allow_destructive_actions' => true]);

        $oldPath = $this->createLogFile('laravel-2026-01-01.log', "Very old log\n");
        touch($oldPath, time() - (90 * 86400));

        $report = $this->cleanup->executeRetentionCleanup(30, archiveBeforeDelete: false);

        $this->assertSame(1, $report['cleaned_files']);
        $this->assertFileDoesNotExist($oldPath);
    }
}
