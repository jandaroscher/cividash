<?php

namespace Tests\Feature\Filament;

use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::firstOrCreate(
            ['slug' => 'default'],
            ['name' => 'Default Dashboard']
        );

        $this->user = User::factory()->create();
        $this->user->tenants()->sync([$this->tenant->id]);
    }

    public function test_admin_dashboard_contains_cividash_logo_and_favicon(): void
    {
        $this->actingAs($this->user);
        Filament::auth()->login($this->user);
        Filament::setTenant($this->tenant);

        $response = $this->followingRedirects()->get('/admin');

        $response->assertStatus(200);
        $response->assertSee('images/branding/cividash-logo.svg', false);
        $response->assertSee('cividash-favicon.png', false);
    }

    public function test_login_page_contains_cividash_logo(): void
    {
        $response = $this->get('/admin/login');

        $response->assertStatus(200);
        $response->assertSee('images/branding/cividash-logo.svg', false);
    }

    public function test_branding_assets_exist(): void
    {
        $this->assertFileExists(public_path('images/branding/cividash-logo.svg'));
        $this->assertFileExists(public_path('images/branding/cividash-logo-dark.svg'));
        $this->assertFileExists(public_path('images/branding/cividash-favicon.png'));
    }
}
