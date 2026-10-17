<?php

namespace MehrabHossain\LogLens\Tests\Feature;

use Illuminate\Support\Facades\File;
use MehrabHossain\LogLens\Tests\TestCase;

class ConfigurationTest extends TestCase
{
    public function test_default_configuration_is_safe_and_disabled(): void
    {
        // 1. Monitoring must be disabled by default
        $this->assertFalse(config('loglens.enabled'));

        // 2. Automatic log deletion must be disabled by default
        $this->assertFalse(config('loglens.cleanup.auto_delete'));

        // 3. Destructive actions must be disabled by default
        $this->assertFalse(config('loglens.cleanup.allow_destructive_actions'));

        // 4. Strict database mode must be enabled by default with no bindings collection
        $this->assertTrue(config('loglens.database.strict_mode'));
        $this->assertFalse(config('loglens.database.collect_bindings'));
        $this->assertFalse(config('loglens.database.persist_metrics'));

        // 5. Default path is loglens
        $this->assertSame('loglens', config('loglens.path'));

        // 6. Sensitive keys redaction must be enabled
        $this->assertTrue(config('loglens.security.redact_sensitive_data'));
        $this->assertContains('password', config('loglens.security.sensitive_keys'));
    }

    public function test_configuration_can_be_published(): void
    {
        $publishedConfig = config_path('loglens.php');

        if (File::exists($publishedConfig)) {
            File::delete($publishedConfig);
        }

        $this->artisan('vendor:publish', ['--tag' => 'loglens-config'])
            ->assertExitCode(0);

        $this->assertFileExists($publishedConfig);

        // Cleanup after test
        if (File::exists($publishedConfig)) {
            File::delete($publishedConfig);
        }
    }

    public function test_views_can_be_published(): void
    {
        $publishedViews = resource_path('views/vendor/loglens');

        if (File::isDirectory($publishedViews)) {
            File::deleteDirectory($publishedViews);
        }

        $this->artisan('vendor:publish', ['--tag' => 'loglens-views'])
            ->assertExitCode(0);

        $this->assertDirectoryExists($publishedViews);

        // Cleanup after test
        if (File::isDirectory($publishedViews)) {
            File::deleteDirectory($publishedViews);
        }
    }
}
