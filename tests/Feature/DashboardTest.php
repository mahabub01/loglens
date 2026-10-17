<?php

namespace MehrabHossain\LogLens\Tests\Feature;

use MehrabHossain\LogLens\LogLens;
use MehrabHossain\LogLens\Tests\TestCase;

class DashboardTest extends TestCase
{
    public function test_dashboard_returns_404_when_package_is_disabled(): void
    {
        config(['loglens.enabled' => false]);

        $response = $this->get('/loglens');

        $response->assertStatus(404);
    }

    public function test_dashboard_returns_403_when_package_is_enabled_but_unauthorized(): void
    {
        $this->enablePackage();

        // In testing environment with no callback/gate defined, access must be denied
        $response = $this->get('/loglens');

        $response->assertStatus(403);
    }

    public function test_dashboard_returns_200_and_renders_for_authorized_users(): void
    {
        $this->enablePackage();
        $this->authorizeAccess();

        $response = $this->get('/loglens');

        $response->assertStatus(200);
        $response->assertViewIs('loglens::dashboard');
    }

    public function test_dashboard_uses_bootstrap_and_font_awesome_and_not_tailwind(): void
    {
        $this->enablePackage();
        $this->authorizeAccess();

        $response = $this->get('/loglens');

        $content = $response->getContent();

        // Must include Bootstrap 5 and Font Awesome
        $this->assertStringContainsString('bootstrap', $content);
        $this->assertStringContainsString('font-awesome', $content);
        $this->assertStringContainsString('chart.js', strtolower($content));

        // Must NOT use Tailwind CSS
        $this->assertStringNotContainsString('tailwindcss', $content);
        $this->assertStringNotContainsString('tailwind.css', $content);
    }

    public function test_dashboard_contains_all_required_navigation_links(): void
    {
        $this->enablePackage();
        $this->authorizeAccess();

        $response = $this->get('/loglens');

        $response->assertSee('Overview');
        $response->assertSee('Logs');
        $response->assertSee('Errors');
        $response->assertSee('HTTP');
        $response->assertSee('Database');
        $response->assertSee('Apache');
        $response->assertSee('Server Health');
        $response->assertSee('Queue');
        $response->assertSee('Settings');
    }

    public function test_dashboard_displays_explicit_placeholder_states_and_not_fabricated_data(): void
    {
        $this->enablePackage();
        $this->authorizeAccess();

        $response = $this->get('/loglens');

        // Verify explicit placeholder / disabled / N/A badges
        $response->assertSee('N/A');
        $response->assertSee('Standby');
        $response->assertSee('Zero-Query Safe');
        $response->assertSee('Phase 1: Package Foundation Active');
    }

    public function test_dashboard_displays_environment_badge(): void
    {
        $this->enablePackage();
        $this->authorizeAccess();

        $response = $this->get('/loglens');

        $response->assertSee('TESTING');
    }
}
