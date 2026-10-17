<?php

namespace MehrabHossain\LogLens\Tests\Security;

use Illuminate\Support\Facades\Gate;
use MehrabHossain\LogLens\LogLens;
use MehrabHossain\LogLens\Tests\TestCase;

class AuthorizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->enablePackage();
    }

    public function test_unauthorized_user_receives_403_forbidden(): void
    {
        // No auth callback registered, non-local env
        $response = $this->get('/loglens');

        $response->assertStatus(403);
    }

    public function test_gate_based_authorization(): void
    {
        // Define Gate 'viewLogLens' returning false
        Gate::define('viewLogLens', fn () => false);

        $this->get('/loglens')->assertStatus(403);

        // Define Gate 'viewLogLens' returning true
        Gate::define('viewLogLens', fn () => true);

        $this->get('/loglens')->assertStatus(200);
    }

    public function test_custom_callback_authorization(): void
    {
        LogLens::auth(function ($request) {
            return $request->header('X-Developer-Token') === 'secret-valid-token';
        });

        // Request without header
        $this->get('/loglens')->assertStatus(403);

        // Request with valid header
        $this->withHeaders(['X-Developer-Token' => 'secret-valid-token'])
            ->get('/loglens')
            ->assertStatus(200);
    }

    public function test_destructive_route_rejects_when_destructive_config_is_disabled(): void
    {
        $this->authorizeAccess();

        // Destructive actions disabled in config
        config(['loglens.cleanup.allow_destructive_actions' => false]);
        LogLens::destructiveAuth(fn () => true);

        $response = $this->postJson('/loglens/api/actions/execute', [
            'action' => 'clear_logs',
            'confirmed' => true,
        ]);

        $response->assertStatus(403);
    }

    public function test_destructive_route_rejects_unauthorized_user(): void
    {
        $this->authorizeAccess();
        config(['loglens.cleanup.allow_destructive_actions' => true]);

        // Destructive callback returns false
        LogLens::destructiveAuth(fn () => false);

        $response = $this->postJson('/loglens/api/actions/execute', [
            'action' => 'clear_logs',
            'confirmed' => true,
        ]);

        $response->assertStatus(403);
    }

    public function test_destructive_route_validates_confirmation_requirement(): void
    {
        $this->authorizeAccess();
        $this->authorizeDestructiveAccess();

        // Missing or false confirmation parameter
        $response = $this->postJson('/loglens/api/actions/execute', [
            'action' => 'clear_logs',
            'confirmed' => false,
        ]);

        $response->assertStatus(422);
    }

    public function test_destructive_route_executes_when_authorized_and_confirmed(): void
    {
        $this->authorizeAccess();
        $this->authorizeDestructiveAccess();

        $response = $this->postJson('/loglens/api/actions/execute', [
            'action' => 'clear_logs',
            'confirmed' => true,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'action' => 'clear_logs',
        ]);
    }
}
