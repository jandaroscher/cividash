<?php

namespace Tests\Feature\Filament\Settings;

use App\Filament\Pages\ManageBranding;
use App\Models\Tenant;
use App\Models\User;
use App\Settings\BrandingSettings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class ManageBrandingTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::create(['name' => 'Test Tenant', 'slug' => 'test-tenant']);
        $this->user = User::factory()->admin()->create();
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
        Livewire::test(ManageBranding::class)
            ->assertSuccessful();
    }

    public function test_color_fields_save_correctly(): void
    {
        Livewire::test(ManageBranding::class)
            ->fillForm([
                'primary_color' => '#FF0000',
                'secondary_color' => '#00FF00',
                'accent_color' => '#0000FF',
                'slider_colors.rail' => '#AAAAAA',
                'slider_colors.handle' => '#BBBBBB',
                'slider_colors.handleBorder' => '#CCCCCC',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = app(BrandingSettings::class);
        $this->assertEquals('#FF0000', $settings->primary_color);
        $this->assertEquals('#00FF00', $settings->secondary_color);
        $this->assertEquals('#0000FF', $settings->accent_color);
    }

    public function test_logo_upload_rejects_file_exceeding_size_limit(): void
    {
        // Logo limit is 2 MB (2048 KB, see ManageBranding::form()).
        $oversizedLogo = UploadedFile::fake()->image('logo.png')->size(2049);

        Livewire::test(ManageBranding::class)
            ->fillForm([
                'logo_url' => [$oversizedLogo],
            ])
            ->call('save')
            ->assertHasFormErrors(['logo_url']);
    }

    public function test_primary_and_secondary_color_are_required(): void
    {
        Livewire::test(ManageBranding::class)
            ->fillForm([
                'primary_color' => '',
                'secondary_color' => '',
                'slider_colors.rail' => '#AAAAAA',
                'slider_colors.handle' => '#BBBBBB',
                'slider_colors.handleBorder' => '#CCCCCC',
            ])
            ->call('save')
            ->assertHasFormErrors(['primary_color', 'secondary_color']);
    }

    public function test_slider_colors_are_required(): void
    {
        Livewire::test(ManageBranding::class)
            ->fillForm([
                'primary_color' => '#FF0000',
                'secondary_color' => '#00FF00',
                'slider_colors.rail' => '',
                'slider_colors.handle' => '',
                'slider_colors.handleBorder' => '',
            ])
            ->call('save')
            ->assertHasFormErrors([
                'slider_colors.rail',
                'slider_colors.handle',
                'slider_colors.handleBorder',
            ]);
    }

    public function test_font_family_saves(): void
    {
        Livewire::test(ManageBranding::class)
            ->fillForm([
                'primary_color' => '#FF0000',
                'secondary_color' => '#00FF00',
                'typography_font_family' => 'Roboto',
                'slider_colors.rail' => '#AAAAAA',
                'slider_colors.handle' => '#BBBBBB',
                'slider_colors.handleBorder' => '#CCCCCC',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = app(BrandingSettings::class);
        $this->assertEquals('Roboto', $settings->typography_font_family);
    }

    public function test_background_colors_save(): void
    {
        Livewire::test(ManageBranding::class)
            ->fillForm([
                'primary_color' => '#FF0000',
                'secondary_color' => '#00FF00',
                'background_color' => '#F5F5F5',
                'card_background_color' => '#FAFAFA',
                'hero_background_color' => '#333333',
                'header_background_color' => '#EEEEEE',
                'footer_background_color' => '#DDDDDD',
                'slider_colors.rail' => '#AAAAAA',
                'slider_colors.handle' => '#BBBBBB',
                'slider_colors.handleBorder' => '#CCCCCC',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = app(BrandingSettings::class);
        $this->assertEquals('#F5F5F5', $settings->background_color);
        $this->assertEquals('#FAFAFA', $settings->card_background_color);
        $this->assertEquals('#333333', $settings->hero_background_color);
        $this->assertEquals('#EEEEEE', $settings->header_background_color);
        $this->assertEquals('#DDDDDD', $settings->footer_background_color);
    }

    public function test_text_colors_save(): void
    {
        Livewire::test(ManageBranding::class)
            ->fillForm([
                'primary_color' => '#FF0000',
                'secondary_color' => '#00FF00',
                'text_primary_color' => '#111111',
                'text_secondary_color' => '#666666',
                'text_inverse_color' => '#FFFFFF',
                'link_color' => '#0066CC',
                'link_hover_color' => '#003399',
                'slider_colors.rail' => '#AAAAAA',
                'slider_colors.handle' => '#BBBBBB',
                'slider_colors.handleBorder' => '#CCCCCC',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = app(BrandingSettings::class);
        $this->assertEquals('#111111', $settings->text_primary_color);
        $this->assertEquals('#666666', $settings->text_secondary_color);
        $this->assertEquals('#FFFFFF', $settings->text_inverse_color);
        $this->assertEquals('#0066CC', $settings->link_color);
        $this->assertEquals('#003399', $settings->link_hover_color);
    }

    public function test_navigation_colors_save(): void
    {
        Livewire::test(ManageBranding::class)
            ->fillForm([
                'primary_color' => '#FF0000',
                'secondary_color' => '#00FF00',
                'nav_text_color' => '#374151',
                'nav_text_color_inactive' => '#9CA3AF',
                'nav_hover_color' => '#FCA5A5',
                'slider_colors.rail' => '#AAAAAA',
                'slider_colors.handle' => '#BBBBBB',
                'slider_colors.handleBorder' => '#CCCCCC',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = app(BrandingSettings::class);
        $this->assertEquals('#374151', $settings->nav_text_color);
        $this->assertEquals('#9CA3AF', $settings->nav_text_color_inactive);
        $this->assertEquals('#FCA5A5', $settings->nav_hover_color);
    }

    public function test_border_shadow_colors_save(): void
    {
        Livewire::test(ManageBranding::class)
            ->fillForm([
                'primary_color' => '#FF0000',
                'secondary_color' => '#00FF00',
                'border_color' => '#E5E7EB',
                'divider_color' => '#D1D5DB',
                'shadow_color' => '#000000',
                'slider_colors.rail' => '#AAAAAA',
                'slider_colors.handle' => '#BBBBBB',
                'slider_colors.handleBorder' => '#CCCCCC',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = app(BrandingSettings::class);
        $this->assertEquals('#E5E7EB', $settings->border_color);
        $this->assertEquals('#D1D5DB', $settings->divider_color);
        $this->assertEquals('#000000', $settings->shadow_color);
    }

    public function test_font_sizes_save_as_individual_fields(): void
    {
        Livewire::test(ManageBranding::class)
            ->fillForm([
                'primary_color' => '#FF0000',
                'secondary_color' => '#00FF00',
                'slider_colors.rail' => '#AAAAAA',
                'slider_colors.handle' => '#BBBBBB',
                'slider_colors.handleBorder' => '#CCCCCC',
                'typography_font_sizes.base' => '1rem',
                'typography_font_sizes.h1' => '3rem',
                'typography_font_sizes.small' => '0.875rem',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = app(BrandingSettings::class);
        $this->assertEquals('1rem', $settings->typography_font_sizes['base']);
        $this->assertEquals('3rem', $settings->typography_font_sizes['h1']);
        $this->assertEquals('0.875rem', $settings->typography_font_sizes['small']);
    }

    public function test_form_loads_existing_settings(): void
    {
        $settings = app(BrandingSettings::class);
        $settings->primary_color = '#ABCDEF';
        $settings->secondary_color = '#FEDCBA';
        $settings->save();

        Livewire::test(ManageBranding::class)
            ->assertFormSet([
                'primary_color' => '#ABCDEF',
                'secondary_color' => '#FEDCBA',
            ]);
    }
}
