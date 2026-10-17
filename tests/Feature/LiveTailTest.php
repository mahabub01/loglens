<?php

namespace MehrabHossain\LogLens\Tests\Feature;

use MehrabHossain\LogLens\Contracts\LogFileManagerInterface;
use MehrabHossain\LogLens\Tests\TestCase;

class LiveTailTest extends TestCase
{
    protected LogFileManagerInterface $fileManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enablePackage();
        $this->fileManager = app(LogFileManagerInterface::class);
    }

    public function test_unauthorized_user_cannot_access_live_tail(): void
    {
        $response = $this->getJson('/loglens/api/logs/tail?file=test');

        $response->assertStatus(403);
    }

    public function test_live_tail_streams_incremental_updates(): void
    {
        $this->authorizeAccess();

        $path = $this->createLogFile('stream.log', "[2026-10-17 12:00:00] local.INFO: Line one\n");
        $files = $this->fileManager->getLogFiles();
        $file = reset($files);

        // Initial tail read
        $response1 = $this->getJson("/loglens/api/logs/tail?file={$file->identifier}&offset=0");

        $response1->assertStatus(200);
        $response1->assertJson([
            'success' => true,
            'file' => 'stream.log',
        ]);
        $newOffset = $response1->json('new_offset');
        $this->assertCount(1, $response1->json('entries'));

        // Second tail read with latest offset (no changes yet)
        $response2 = $this->getJson("/loglens/api/logs/tail?file={$file->identifier}&offset={$newOffset}");
        $response2->assertStatus(200);
        $this->assertEmpty($response2->json('entries'));

        // Append newly arrived log line
        file_put_contents($path, "[2026-10-17 12:01:00] local.ERROR: Live streamed error!\n", FILE_APPEND);

        // Third tail read picks up new line
        $response3 = $this->getJson("/loglens/api/logs/tail?file={$file->identifier}&offset={$newOffset}");
        $response3->assertStatus(200);
        $this->assertCount(1, $response3->json('entries'));
        $this->assertSame('Live streamed error!', $response3->json('entries.0.message'));
    }
}
