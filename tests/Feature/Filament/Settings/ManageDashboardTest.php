<?php

namespace Tests\Feature\Filament\Settings;

use App\Filament\Pages\ManageDashboard;
use App\Models\Tenant;
use App\Models\User;
use App\Settings\DashboardSettings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManageDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::create(['name' => 'Test Tenant', 'slug' => 'test-tenant']);
        $this->user = User::factory()->create();
        $this->user->tenants()->attach($this->tenant->id);
        $this->actingAs($this->user);
        Filament::setTenant($this->tenant);
    }

    protected function tearDown(): void
    {
        Filament::setTenant(null);
        parent::tearDown();
    }

    public function test_page_renders(): void
    {
        Livewire::test(ManageDashboard::class)
            ->assertSuccessful();
    }

    public function test_content_links_save(): void
    {
        Livewire::test(ManageDashboard::class)
            ->fillForm([
                'open_source_docs_url' => 'https://example.com/docs',
                'user_manual_url' => 'https://example.com/manual',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = app(DashboardSettings::class);
        $this->assertEquals('https://example.com/docs', $settings->open_source_docs_url);
        $this->assertEquals('https://example.com/manual', $settings->user_manual_url);
    }

    public function test_content_links_must_be_valid_urls(): void
    {
        Livewire::test(ManageDashboard::class)
            ->fillForm([
                'open_source_docs_url' => 'not-a-url',
            ])
            ->call('save')
            ->assertHasFormErrors(['open_source_docs_url']);
    }

    public function test_contact_fields_save(): void
    {
        Livewire::test(ManageDashboard::class)
            ->fillForm([
                'contact_name' => 'John Doe',
                'contact_email' => 'john@example.com',
                'contact_url' => 'https://example.com/contact',
                'made_with_text' => 'Made with love',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = app(DashboardSettings::class);
        $this->assertEquals('John Doe', $settings->contact_name);
        $this->assertEquals('john@example.com', $settings->contact_email);
        $this->assertEquals('https://example.com/contact', $settings->contact_url);
        $this->assertEquals('Made with love', $settings->made_with_text);
    }

    public function test_contact_email_must_be_valid(): void
    {
        Livewire::test(ManageDashboard::class)
            ->fillForm([
                'contact_email' => 'not-an-email',
            ])
            ->call('save')
            ->assertHasFormErrors(['contact_email']);
    }

    public function test_contact_url_must_be_valid(): void
    {
        Livewire::test(ManageDashboard::class)
            ->fillForm([
                'contact_url' => 'not-a-url',
            ])
            ->call('save')
            ->assertHasFormErrors(['contact_url']);
    }

    public function test_show_server_time_toggle_saves(): void
    {
        Livewire::test(ManageDashboard::class)
            ->fillForm([
                'show_server_time' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = app(DashboardSettings::class);
        $this->assertTrue($settings->show_server_time);

        // Toggle off
        Livewire::test(ManageDashboard::class)
            ->fillForm([
                'show_server_time' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        app()->forgetInstance(DashboardSettings::class);
        $settings = app(DashboardSettings::class);
        $this->assertFalse($settings->show_server_time);
    }

    public function test_form_loads_existing_settings(): void
    {
        $settings = app(DashboardSettings::class);
        $settings->contact_name = 'Existing Contact';
        $settings->open_source_docs_url = 'https://docs.example.com';
        $settings->show_server_time = true;
        $settings->save();

        Livewire::test(ManageDashboard::class)
            ->assertFormSet([
                'contact_name' => 'Existing Contact',
                'open_source_docs_url' => 'https://docs.example.com',
                'show_server_time' => true,
            ]);
    }

    public function test_nullable_fields_can_be_cleared(): void
    {
        // First set values
        $settings = app(DashboardSettings::class);
        $settings->contact_name = 'Test Name';
        $settings->contact_email = 'test@example.com';
        $settings->save();

        // Clear them
        Livewire::test(ManageDashboard::class)
            ->fillForm([
                'contact_name' => null,
                'contact_email' => null,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        app()->forgetInstance(DashboardSettings::class);
        $settings = app(DashboardSettings::class);
        $this->assertNull($settings->contact_name);
        $this->assertNull($settings->contact_email);
    }
}
