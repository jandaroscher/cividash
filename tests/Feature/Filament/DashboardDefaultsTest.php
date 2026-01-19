<?php

namespace Tests\Feature\Filament;

use App\Settings\DashboardSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardDefaultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_settings_defaults_match_seeded_values(): void
    {
        $settings = app(DashboardSettings::class);

        $this->assertSame('https://www.example.org/kontakt', $settings->open_source_docs_url);
        $this->assertSame('https://www.example.org/kontakt', $settings->user_manual_url);
        $this->assertNull($settings->contact_name);
        $this->assertSame('support@example.org', $settings->contact_email);
        $this->assertSame('https://www.example.org/kontakt', $settings->contact_url);
        $this->assertTrue($settings->show_server_time);
        $this->assertNull($settings->made_with_text);
    }
}
