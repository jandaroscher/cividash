<?php

namespace Tests\Feature\Filament\Settings;

use App\Filament\Pages\ManageApiKeys;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Livewire page-level tests for ManageApiKeys.
 *
 * Note: Service-level token creation, revocation, listing, and ability validation
 * are already covered in Tests\Feature\Filament\ApiKeysManagementTest.
 * This file focuses on the Livewire page rendering and table interactions.
 */
class ManageApiKeysTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::create(['name' => 'Test Tenant', 'slug' => 'test-tenant']);
        $this->user = User::factory()->admin()->create(['admin_api_enabled' => true]);
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
        Livewire::test(ManageApiKeys::class)
            ->assertSuccessful();
    }

    public function test_page_shows_empty_state_when_no_tokens(): void
    {
        Livewire::test(ManageApiKeys::class)
            ->assertSuccessful()
            ->assertSee(__('filament.pages.manage_api_keys.no_tokens'));
    }

    public function test_table_displays_token_for_current_tenant(): void
    {
        $token = $this->user->createToken('Test Token', ['public-read']);
        $token->accessToken->tenant_id = $this->tenant->id;
        $token->accessToken->save();

        Livewire::test(ManageApiKeys::class)
            ->assertCanSeeTableRecords([$token->accessToken]);
    }

    public function test_table_does_not_show_tokens_from_other_tenants(): void
    {
        $otherTenant = Tenant::create(['name' => 'Other Tenant', 'slug' => 'other-tenant']);

        $otherToken = $this->user->createToken('Other Token', ['public-read']);
        $otherToken->accessToken->tenant_id = $otherTenant->id;
        $otherToken->accessToken->save();

        Livewire::test(ManageApiKeys::class)
            ->assertCanNotSeeTableRecords([$otherToken->accessToken]);
    }

    public function test_clear_token_display_resets_state(): void
    {
        Livewire::test(ManageApiKeys::class)
            ->set('newTokenPlainText', 'some-token-value')
            ->set('newTokenName', 'Test Token')
            ->call('clearTokenDisplay')
            ->assertSet('newTokenPlainText', null)
            ->assertSet('newTokenName', null);
    }
}
