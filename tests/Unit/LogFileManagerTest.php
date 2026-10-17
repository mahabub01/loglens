<?php

namespace MehrabHossain\LogLens\Tests\Unit;

use MehrabHossain\LogLens\Contracts\LogFileManagerInterface;
use MehrabHossain\LogLens\Exceptions\LogFileNotFoundException;
use MehrabHossain\LogLens\Exceptions\LogLensSecurityException;
use MehrabHossain\LogLens\Services\LogFileManager;
use MehrabHossain\LogLens\Tests\TestCase;
use MehrabHossain\LogLens\ValueObjects\LogFile;

class LogFileManagerTest extends TestCase
{
    protected LogFileManagerInterface $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = app(LogFileManagerInterface::class);
    }

    public function test_lists_authorized_log_files(): void
    {
        $this->createLogFile('laravel.log', "Sample log content\n");
        $this->createLogFile('laravel-2026-10-17.log', "Daily log content\n");

        $files = $this->manager->getLogFiles();

        $this->assertCount(2, $files);
        $filenames = array_map(fn (LogFile $f) => $f->filename, $files);
        $this->assertContains('laravel.log', $filenames);
        $this->assertContains('laravel-2026-10-17.log', $filenames);
    }

    public function test_ignores_disallowed_file_extensions(): void
    {
        $this->createLogFile('valid.log', "Valid log content\n");
        $this->createLogFile('secret.env', "SECRET=value\n");
        $this->createLogFile('script.php', "<?php echo 'hi';\n");
        $this->createLogFile('notes.txt', "Notes\n");

        $files = $this->manager->getLogFiles();

        $this->assertCount(1, $files);
        $file = reset($files);
        $this->assertSame('valid.log', $file->filename);
    }

    public function test_resolves_file_by_identifier(): void
    {
        $this->createLogFile('app.log', "App log\n");

        $files = $this->manager->getLogFiles();
        $identifier = array_key_first($files);

        $resolved = $this->manager->resolveFile($identifier);

        $this->assertSame('app.log', $resolved->filename);
        $this->assertSame($identifier, $resolved->identifier);
    }

    public function test_resolve_file_throws_security_exception_on_traversal_identifier(): void
    {
        $this->expectException(LogLensSecurityException::class);

        $this->manager->resolveFile('../etc/passwd');
    }

    public function test_resolve_file_throws_not_found_exception_on_unknown_identifier(): void
    {
        $this->expectException(LogFileNotFoundException::class);

        $this->manager->resolveFile('nonexistent_id');
    }

    public function test_supports_additional_read_only_paths(): void
    {
        $readOnlyDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ro_logs_' . uniqid();
        @mkdir($readOnlyDir, 0777, true);
        file_put_contents($readOnlyDir . DIRECTORY_SEPARATOR . 'apache_access.log', "127.0.0.1 - GET\n");

        config(['loglens.logs.additional_paths' => [$readOnlyDir]]);

        $files = $this->manager->getLogFiles();

        $this->assertCount(1, $files);
        $file = reset($files);
        $this->assertSame('apache_access.log', $file->filename);
        $this->assertTrue($file->isReadOnly);

        // Cleanup
        @unlink($readOnlyDir . DIRECTORY_SEPARATOR . 'apache_access.log');
        @rmdir($readOnlyDir);
    }
}
