<?php

namespace Tests\Feature\Filament\Settings;

use App\Filament\Pages\ManageApiKeys;
use App\Models\PersonalAccessToken;
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
        Livewire::test(ManageApiKeys::class)
            ->assertSuccessful();
    }

    public function test_create_action_stores_the_chosen_expiry_date(): void
    {
        Livewire::test(ManageApiKeys::class)
            ->callAction('create', [
                'name' => 'Partner feed',
                'abilities' => 'public-read',
                'expires_at' => '2027-03-31',
            ])
            ->assertHasNoActionErrors();

        $token = PersonalAccessToken::where('name', 'Partner feed')->firstOrFail();
        $this->assertSame('2027-03-31', $token->expires_at->toDateString());
    }

    public function test_create_action_defaults_to_the_configured_lifetime(): void
    {
        config(['sanctum.token_lifetime_days' => 90]);
        $this->freezeSecond();

        Livewire::test(ManageApiKeys::class)
            ->mountAction('create')
            ->assertActionDataSet(['expires_at' => now()->addDays(90)->toDateString()]);
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

    public function test_abilities_filter_matches_tokens_by_ability(): void
    {
        // Exercises the abilities SelectFilter, which queries the Sanctum
        // `abilities` text column. On PostgreSQL whereJsonContains() errors
        // (text @> jsonb); the driver-aware abilities::jsonb @> cast must work.
        $readToken = $this->user->createToken('Read Token', ['public-read']);
        $readToken->accessToken->tenant_id = $this->tenant->id;
        $readToken->accessToken->save();

        $writeToken = $this->user->createToken('Write Token', ['admin-write']);
        $writeToken->accessToken->tenant_id = $this->tenant->id;
        $writeToken->accessToken->save();

        Livewire::test(ManageApiKeys::class)
            ->filterTable('abilities', 'public-read')
            ->assertCanSeeTableRecords([$readToken->accessToken])
            ->assertCanNotSeeTableRecords([$writeToken->accessToken]);
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
