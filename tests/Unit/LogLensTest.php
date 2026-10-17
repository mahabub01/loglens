<?php

namespace MehrabHossain\LogLens\Tests\Unit;

use Illuminate\Support\Facades\Gate;
use MehrabHossain\LogLens\Contracts\AuthorizerInterface;
use MehrabHossain\LogLens\Facades\LogLens as LogLensFacade;
use MehrabHossain\LogLens\LogLens;
use MehrabHossain\LogLens\Tests\TestCase;

class LogLensTest extends TestCase
{
    public function test_package_version_is_defined(): void
    {
        $this->assertSame('1.0.0', LogLens::version());
        $this->assertSame('1.0.0', LogLensFacade::version());
    }

    public function test_is_enabled_reflects_configuration(): void
    {
        config(['loglens.enabled' => false]);
        $this->assertFalse(LogLens::isEnabled());

        config(['loglens.enabled' => true]);
        $this->assertTrue(LogLens::isEnabled());
    }

    public function test_path_reflects_configuration(): void
    {
        config(['loglens.path' => 'custom-monitor']);
        $this->assertSame('custom-monitor', LogLens::path());
    }

    public function test_auth_callback_authorizes_access(): void
    {
        config(['loglens.enabled' => true]);

        // When no callback and non-local environment (testing != local)
        $this->assertFalse(LogLens::check());

        // When callback returns true
        LogLens::auth(function ($request) {
            return true;
        });
        $this->assertTrue(LogLens::check());

        // When callback returns false
        LogLens::auth(function ($request) {
            return false;
        });
        $this->assertFalse(LogLens::check());
    }

    public function test_destructive_auth_callback_requires_both_config_and_callback(): void
    {
        // 1. If config allow_destructive_actions is false, destructive auth is always false
        config(['loglens.cleanup.allow_destructive_actions' => false]);
        LogLens::destructiveAuth(fn () => true);
        $this->assertFalse(LogLens::checkDestructive());

        // 2. If config is true and callback returns true
        config(['loglens.cleanup.allow_destructive_actions' => true]);
        $this->assertTrue(LogLens::checkDestructive());

        // 3. If callback returns false
        LogLens::destructiveAuth(fn () => false);
        $this->assertFalse(LogLens::checkDestructive());
    }

    public function test_gate_integration_for_authorization(): void
    {
        // Reset callbacks
        LogLens::resetAuth();

        Gate::define('viewLogLens', function () {
            return true;
        });

        $this->assertTrue(LogLens::check());

        Gate::define('viewLogLens', function () {
            return false;
        });

        $this->assertFalse(LogLens::check());
    }

    public function test_script_variables_structure(): void
    {
        $vars = LogLens::scriptVariables();

        $this->assertArrayHasKey('path', $vars);
        $this->assertArrayHasKey('version', $vars);
        $this->assertArrayHasKey('enabled', $vars);
        $this->assertArrayHasKey('environment', $vars);
        $this->assertArrayHasKey('csrfToken', $vars);
    }
}
