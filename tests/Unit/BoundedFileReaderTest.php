<?php

namespace MehrabHossain\LogLens\Tests\Unit;

use MehrabHossain\LogLens\Contracts\LogFileManagerInterface;
use MehrabHossain\LogLens\Contracts\LogReaderInterface;
use MehrabHossain\LogLens\Tests\TestCase;

class BoundedFileReaderTest extends TestCase
{
    protected LogReaderInterface $reader;
    protected LogFileManagerInterface $fileManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reader = app(LogReaderInterface::class);
        $this->fileManager = app(LogFileManagerInterface::class);
    }

    public function test_reads_paginated_entries_newest_first(): void
    {
        $logContent = <<<'LOG'
[2026-10-17 10:00:00] local.INFO: First log entry
[2026-10-17 10:05:00] local.WARNING: Second log entry
[2026-10-17 10:10:00] local.ERROR: Third log entry
LOG;

        $path = $this->createLogFile('app.log', $logContent);
        $logFile = $this->fileManager->getLogFiles()[array_key_first($this->fileManager->getLogFiles())];

        $result = $this->reader->readPaginated($logFile, page: 1, perPage: 2);

        $this->assertCount(2, $result['entries']);
        // Newest entry should be first
        $this->assertSame('Third log entry', $result['entries'][0]->message);
        $this->assertSame('Second log entry', $result['entries'][1]->message);
        $this->assertTrue($result['has_more']);

        // Page 2
        $resultPage2 = $this->reader->readPaginated($logFile, page: 2, perPage: 2);
        $this->assertCount(1, $resultPage2['entries']);
        $this->assertSame('First log entry', $resultPage2['entries'][0]->message);
        $this->assertFalse($resultPage2['has_more']);
    }

    public function test_handles_empty_file_without_errors(): void
    {
        $this->createLogFile('empty.log', '');
        $logFile = $this->fileManager->getLogFiles()[array_key_first($this->fileManager->getLogFiles())];

        $result = $this->reader->readPaginated($logFile);

        $this->assertEmpty($result['entries']);
        $this->assertFalse($result['has_more']);
        $this->assertSame(0, $result['file_size']);
    }

    public function test_incremental_read_from_offset_for_live_tail(): void
    {
        $initialContent = "[2026-10-17 11:00:00] local.INFO: Initial entry\n";
        $path = $this->createLogFile('live.log', $initialContent);
        $logFile = $this->fileManager->getLogFiles()[array_key_first($this->fileManager->getLogFiles())];

        $initialOffset = strlen($initialContent);

        // Append new line to file
        $newContent = "[2026-10-17 11:01:00] local.ERROR: Newly arrived error\n";
        file_put_contents($path, $newContent, FILE_APPEND);

        // Read only starting from initialOffset
        $tailResult = $this->reader->readFromOffset($logFile, $initialOffset);

        $this->assertCount(1, $tailResult['entries']);
        $this->assertSame('Newly arrived error', $tailResult['entries'][0]->message);
        $this->assertGreaterThan($initialOffset, $tailResult['new_offset']);
        $this->assertFalse($tailResult['was_truncated']);
    }

    public function test_detects_log_truncation_when_offset_exceeds_filesize(): void
    {
        $path = $this->createLogFile('truncated.log', "Old long log content that takes many bytes...\n");
        $logFile = $this->fileManager->getLogFiles()[array_key_first($this->fileManager->getLogFiles())];

        $pastOffset = 5000;

        // Truncate file to shorter size
        file_put_contents($path, "[2026-10-17 12:00:00] local.INFO: Fresh line after truncate\n");

        $result = $this->reader->readFromOffset($logFile, $pastOffset);

        $this->assertTrue($result['was_truncated']);
        $this->assertNotEmpty($result['entries']);
    }
}
