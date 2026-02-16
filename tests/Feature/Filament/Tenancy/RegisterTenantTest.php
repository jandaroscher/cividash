<?php

namespace Tests\Feature\Filament\Tenancy;

use App\Filament\Pages\Tenancy\RegisterTenant;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RegisterTenantTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::where('slug', 'default')->first();
        $this->user = User::factory()->create();
        // User is already attached to default tenant via User::booted()

        $this->actingAs($this->user);
        Filament::setTenant($this->tenant);
    }

    protected function tearDown(): void
    {
        Filament::setTenant(null);
        parent::tearDown();
    }

    public function test_registration_form_renders(): void
    {
        Livewire::test(RegisterTenant::class)
            ->assertSuccessful();
    }

    public function test_registration_creates_tenant_and_attaches_user(): void
    {
        Livewire::test(RegisterTenant::class)
            ->fillForm([
                'name' => 'New Organization',
                'slug' => 'new-organization',
            ])
            ->call('register')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('tenants', [
            'name' => 'New Organization',
            'slug' => 'new-organization',
        ]);

        $newTenant = Tenant::where('slug', 'new-organization')->first();
        $this->assertNotNull($newTenant);

        // Verify user is attached to the new tenant
        $this->assertTrue(
            $this->user->tenants()->where('tenants.id', $newTenant->id)->exists()
        );
    }

    public function test_slug_uniqueness_enforced(): void
    {
        // Create a tenant with a specific slug
        Tenant::create([
            'name' => 'Existing Tenant',
            'slug' => 'existing-slug',
        ]);

        Livewire::test(RegisterTenant::class)
            ->fillForm([
                'name' => 'Another Tenant',
                'slug' => 'existing-slug',
            ])
            ->call('register')
            ->assertHasFormErrors(['slug']);
    }

    public function test_name_is_required(): void
    {
        Livewire::test(RegisterTenant::class)
            ->fillForm([
                'name' => '',
                'slug' => 'some-slug',
            ])
            ->call('register')
            ->assertHasFormErrors(['name' => 'required']);
    }

    public function test_slug_is_required(): void
    {
        Livewire::test(RegisterTenant::class)
            ->fillForm([
                'name' => 'Some Name',
                'slug' => '',
            ])
            ->call('register')
            ->assertHasFormErrors(['slug' => 'required']);
    }

    public function test_default_tenant_set_for_user_without_one(): void
    {
        // Create user without default_tenant_id
        $user = User::factory()->create();
        // The User model's `created` event auto-sets default_tenant_id,
        // so we need to clear it manually to simulate the scenario
        $user->forceFill(['default_tenant_id' => null])->save();
        // User is already attached to default tenant via User::booted()

        $this->actingAs($user);

        $this->assertNull($user->fresh()->default_tenant_id);

        Livewire::test(RegisterTenant::class)
            ->fillForm([
                'name' => 'First Tenant For User',
                'slug' => 'first-tenant-for-user',
            ])
            ->call('register')
            ->assertHasNoFormErrors();

        $newTenant = Tenant::where('slug', 'first-tenant-for-user')->first();
        $user->refresh();

        $this->assertEquals($newTenant->id, $user->default_tenant_id);
    }

    public function test_default_tenant_not_overwritten_if_already_set(): void
    {
        // User already has a default tenant (set by User model boot)
        $originalDefaultId = $this->user->default_tenant_id;
        $this->assertNotNull($originalDefaultId);

        Livewire::test(RegisterTenant::class)
            ->fillForm([
                'name' => 'Second Tenant',
                'slug' => 'second-tenant',
            ])
            ->call('register')
            ->assertHasNoFormErrors();

        $this->user->refresh();

        // Default tenant should remain the original one
        $this->assertEquals($originalDefaultId, $this->user->default_tenant_id);
    }
}
