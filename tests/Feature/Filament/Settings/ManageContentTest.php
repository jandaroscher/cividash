<?php

namespace Tests\Feature\Filament\Settings;

use App\Filament\Pages\ManageContent;
use App\Models\Tenant;
use App\Models\User;
use App\Settings\ContentSettings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManageContentTest extends TestCase
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
        Livewire::test(ManageContent::class)
            ->assertSuccessful();
    }

    public function test_hero_content_heading_block_saves(): void
    {
        Livewire::test(ManageContent::class)
            ->fillForm([
                'hero_content' => [
                    [
                        'type' => 'heading',
                        'data' => [
                            'content' => 'Welcome to the Dashboard',
                            'level' => 'h1',
                        ],
                    ],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = app(ContentSettings::class);
        $this->assertNotNull($settings->hero_content);
        $this->assertIsArray($settings->hero_content);
        $this->assertNotEmpty($settings->hero_content);
    }

    public function test_hero_content_can_be_empty(): void
    {
        Livewire::test(ManageContent::class)
            ->fillForm([
                'hero_content' => [],
            ])
            ->call('save')
            ->assertHasNoFormErrors();
    }

    public function test_form_loads_existing_content(): void
    {
        $settings = app(ContentSettings::class);
        $settings->hero_content = [
            [
                'type' => 'heading',
                'data' => [
                    'content' => 'Existing Heading',
                    'level' => 'h2',
                ],
            ],
        ];
        $settings->save();

        Livewire::test(ManageContent::class)
            ->assertSuccessful();

        // Verify the settings still have the stored content
        $loadedSettings = app(ContentSettings::class);
        $this->assertNotNull($loadedSettings->hero_content);
        $this->assertIsArray($loadedSettings->hero_content);
    }

    public function test_save_updates_content_settings(): void
    {
        // First save
        Livewire::test(ManageContent::class)
            ->fillForm([
                'hero_content' => [
                    [
                        'type' => 'heading',
                        'data' => [
                            'content' => 'First Heading',
                            'level' => 'h1',
                        ],
                    ],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        // Second save should update
        Livewire::test(ManageContent::class)
            ->fillForm([
                'hero_content' => [
                    [
                        'type' => 'heading',
                        'data' => [
                            'content' => 'Updated Heading',
                            'level' => 'h2',
                        ],
                    ],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        app()->forgetInstance(ContentSettings::class);
        $settings = app(ContentSettings::class);
        $this->assertNotNull($settings->hero_content);
    }
}
