<?php

namespace Tests\Feature\Filament\Tenancy;

use App\Filament\Pages\Tenancy\EditTenantProfile;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TenantDomainSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create tenants
        $this->tenantA = Tenant::create([
            'name' => 'Tenant A',
            'slug' => 'tenant-a',
        ]);
        $this->tenantB = Tenant::create([
            'name' => 'Tenant B',
            'slug' => 'tenant-b',
            'domain' => 'tenant-b.example.com',
        ]);

        // Create user and attach to tenants
        $this->user = User::factory()->create();
        $this->user->tenants()->attach([$this->tenantA->id, $this->tenantB->id]);

        // Authenticate the user in Filament context
        $this->actingAs($this->user);
    }

    protected function tearDown(): void
    {
        Filament::setTenant(null);
        parent::tearDown();
    }

    // ========== Persistence Tests ==========

    public function test_domain_and_frontend_url_are_saved_on_active_tenant(): void
    {
        // Set active tenant to Tenant A
        Filament::setTenant($this->tenantA);

        Livewire::test(EditTenantProfile::class)
            ->fillForm([
                'name' => 'Tenant A',
                'slug' => 'tenant-a',
                'domain' => 'tenant-a.example.com',
                'frontend_base_url' => 'https://tenant-a.example.com',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        // Verify the data was saved to Tenant A
        $this->tenantA->refresh();
        $this->assertEquals('tenant-a.example.com', $this->tenantA->domain);
        $this->assertEquals('https://tenant-a.example.com', $this->tenantA->frontend_base_url);

        // Verify Tenant B was not modified
        $this->tenantB->refresh();
        $this->assertEquals('tenant-b.example.com', $this->tenantB->domain);
        $this->assertNull($this->tenantB->frontend_base_url);
    }

    public function test_domain_can_be_set_to_null(): void
    {
        // Set initial domain
        $this->tenantA->update(['domain' => 'initial.example.com']);

        Filament::setTenant($this->tenantA);

        Livewire::test(EditTenantProfile::class)
            ->fillForm([
                'name' => 'Tenant A',
                'slug' => 'tenant-a',
                'domain' => null,
                'frontend_base_url' => null,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->tenantA->refresh();
        $this->assertNull($this->tenantA->domain);
        $this->assertNull($this->tenantA->frontend_base_url);
    }

    // ========== Domain Uniqueness Validation ==========

    public function test_domain_must_be_unique_across_tenants(): void
    {
        // Tenant B already has 'tenant-b.example.com'
        Filament::setTenant($this->tenantA);

        Livewire::test(EditTenantProfile::class)
            ->fillForm([
                'name' => 'Tenant A',
                'slug' => 'tenant-a',
                'domain' => 'tenant-b.example.com', // Already used by Tenant B
            ])
            ->call('save')
            ->assertHasFormErrors(['domain']);
    }

    public function test_domain_uniqueness_ignores_current_tenant(): void
    {
        // Set domain for Tenant A
        $this->tenantA->update(['domain' => 'tenant-a.example.com']);

        Filament::setTenant($this->tenantA);

        // Should NOT fail when saving the same domain for the same tenant
        Livewire::test(EditTenantProfile::class)
            ->fillForm([
                'name' => 'Tenant A Updated',
                'slug' => 'tenant-a',
                'domain' => 'tenant-a.example.com', // Same as current
            ])
            ->call('save')
            ->assertHasNoFormErrors();
    }

    public function test_domain_uniqueness_checks_normalized_value(): void
    {
        // Tenant B has 'tenant-b.example.com'
        Filament::setTenant($this->tenantA);

        // Try to set 'WWW.Tenant-B.Example.COM' (should normalize to same as Tenant B's domain)
        Livewire::test(EditTenantProfile::class)
            ->fillForm([
                'name' => 'Tenant A',
                'slug' => 'tenant-a',
                'domain' => 'WWW.Tenant-B.Example.COM',
            ])
            ->call('save')
            ->assertHasFormErrors(['domain']);
    }

    // ========== Domain Format Validation ==========

    public function test_domain_rejects_url_with_scheme(): void
    {
        Filament::setTenant($this->tenantA);

        Livewire::test(EditTenantProfile::class)
            ->fillForm([
                'name' => 'Tenant A',
                'slug' => 'tenant-a',
                'domain' => 'https://example.com',
            ])
            ->call('save')
            ->assertHasFormErrors(['domain']);
    }

    public function test_domain_rejects_url_with_path(): void
    {
        Filament::setTenant($this->tenantA);

        Livewire::test(EditTenantProfile::class)
            ->fillForm([
                'name' => 'Tenant A',
                'slug' => 'tenant-a',
                'domain' => 'example.com/path',
            ])
            ->call('save')
            ->assertHasFormErrors(['domain']);
    }

    public function test_domain_rejects_invalid_characters(): void
    {
        Filament::setTenant($this->tenantA);

        Livewire::test(EditTenantProfile::class)
            ->fillForm([
                'name' => 'Tenant A',
                'slug' => 'tenant-a',
                'domain' => 'exa mple.com', // Space in domain
            ])
            ->call('save')
            ->assertHasFormErrors(['domain']);
    }

    public function test_domain_accepts_valid_hostname(): void
    {
        Filament::setTenant($this->tenantA);

        Livewire::test(EditTenantProfile::class)
            ->fillForm([
                'name' => 'Tenant A',
                'slug' => 'tenant-a',
                'domain' => 'my-tenant.example.com',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->tenantA->refresh();
        $this->assertEquals('my-tenant.example.com', $this->tenantA->domain);
    }

    public function test_domain_accepts_localhost_for_development(): void
    {
        Filament::setTenant($this->tenantA);

        Livewire::test(EditTenantProfile::class)
            ->fillForm([
                'name' => 'Tenant A',
                'slug' => 'tenant-a',
                'domain' => 'localhost',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->tenantA->refresh();
        $this->assertEquals('localhost', $this->tenantA->domain);
    }

    // ========== Domain Normalization Tests ==========

    public function test_domain_is_normalized_to_lowercase(): void
    {
        Filament::setTenant($this->tenantA);

        Livewire::test(EditTenantProfile::class)
            ->fillForm([
                'name' => 'Tenant A',
                'slug' => 'tenant-a',
                'domain' => 'Example.COM',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->tenantA->refresh();
        $this->assertEquals('example.com', $this->tenantA->domain);
    }

    public function test_domain_strips_www_prefix(): void
    {
        Filament::setTenant($this->tenantA);

        Livewire::test(EditTenantProfile::class)
            ->fillForm([
                'name' => 'Tenant A',
                'slug' => 'tenant-a',
                'domain' => 'www.example.com',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->tenantA->refresh();
        $this->assertEquals('example.com', $this->tenantA->domain);
    }

    public function test_domain_normalization_combined(): void
    {
        Filament::setTenant($this->tenantA);

        Livewire::test(EditTenantProfile::class)
            ->fillForm([
                'name' => 'Tenant A',
                'slug' => 'tenant-a',
                'domain' => '  WWW.Example.COM  ', // Mixed case, www prefix, whitespace
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->tenantA->refresh();
        $this->assertEquals('example.com', $this->tenantA->domain);
    }

    // ========== Frontend Base URL Validation ==========

    public function test_frontend_base_url_must_be_valid_url(): void
    {
        Filament::setTenant($this->tenantA);

        Livewire::test(EditTenantProfile::class)
            ->fillForm([
                'name' => 'Tenant A',
                'slug' => 'tenant-a',
                'frontend_base_url' => 'not-a-url',
            ])
            ->call('save')
            ->assertHasFormErrors(['frontend_base_url']);
    }

    public function test_frontend_base_url_accepts_https(): void
    {
        Filament::setTenant($this->tenantA);

        Livewire::test(EditTenantProfile::class)
            ->fillForm([
                'name' => 'Tenant A',
                'slug' => 'tenant-a',
                'frontend_base_url' => 'https://example.com',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->tenantA->refresh();
        $this->assertEquals('https://example.com', $this->tenantA->frontend_base_url);
    }

    public function test_frontend_base_url_accepts_http(): void
    {
        // HTTP is allowed (for dev environments) but https is preferred
        Filament::setTenant($this->tenantA);

        Livewire::test(EditTenantProfile::class)
            ->fillForm([
                'name' => 'Tenant A',
                'slug' => 'tenant-a',
                'frontend_base_url' => 'http://localhost:3000',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->tenantA->refresh();
        $this->assertEquals('http://localhost:3000', $this->tenantA->frontend_base_url);
    }
}
