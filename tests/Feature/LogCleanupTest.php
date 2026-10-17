<?php

namespace MehrabHossain\LogLens\Tests\Feature;

use MehrabHossain\LogLens\Contracts\LogFileManagerInterface;
use MehrabHossain\LogLens\Tests\TestCase;

class LogCleanupTest extends TestCase
{
    protected LogFileManagerInterface $fileManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enablePackage();
        $this->fileManager = app(LogFileManagerInterface::class);
    }

    public function test_cleanup_preview_returns_candidate_files(): void
    {
        $this->authorizeAccess();

        $oldFile = $this->createLogFile('laravel-2026-06-01.log', "Old content\n");
        touch($oldFile, time() - (60 * 86400));

        $response = $this->postJson('/loglens/api/cleanup/preview', [
            'retention_days' => 30,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'preview' => [
                'files_count' => 1,
            ],
        ]);
    }

    public function test_clear_file_requires_destructive_authorization_and_confirmation(): void
    {
        $this->authorizeAccess();

        $path = $this->createLogFile('laravel.log', "Active log data\n");
        $files = $this->fileManager->getLogFiles();
        $file = reset($files);

        // 1. Without destructive authorization -> 403
        $res1 = $this->postJson('/loglens/api/logs/clear', [
            'file' => $file->identifier,
            'confirmed' => true,
        ]);
        $res1->assertStatus(403);

        // 2. With destructive authorization but without confirmation -> 422
        $this->authorizeDestructiveAccess();
        $res2 = $this->postJson('/loglens/api/logs/clear', [
            'file' => $file->identifier,
            'confirmed' => false,
        ]);
        $res2->assertStatus(422);

        // 3. Authorized and confirmed -> 200 and file truncated
        $res3 = $this->postJson('/loglens/api/logs/clear', [
            'file' => $file->identifier,
            'confirmed' => true,
        ]);
        $res3->assertStatus(200);
        $res3->assertJson(['success' => true]);

        clearstatcache(true, $path);
        $this->assertSame(0, filesize($path));
    }
}
