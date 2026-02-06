<?php

namespace Tests\Feature\Filament;

use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::firstOrCreate(
            ['slug' => 'default'],
            ['name' => 'Default Tenant']
        );

        $this->user = User::factory()->create();
        $this->user->tenants()->sync([$this->tenant->id]);

        $this->actingAs($this->user);
        Filament::auth()->login($this->user);
        Filament::setTenant($this->tenant);
    }

    public function test_manage_dashboard_is_not_listed_in_navigation(): void
    {
        $response = $this->followingRedirects()->get('/admin');

        $response->assertStatus(200);
        $response->assertDontSee('/manage-dashboard', false);
    }
}
