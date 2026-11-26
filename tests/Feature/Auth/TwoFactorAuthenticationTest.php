<?php

// SPDX-FileCopyrightText: 2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace Tests\Feature\Auth;

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Tenancy\EditTenantProfile;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Integration\KeycloakSsoService;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    private const PASSWORD = 'correct-horse-battery';

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function app2fa(): AppAuthentication
    {
        return Filament::getMultiFactorAuthenticationProviders()['app'];
    }

    private function enableTwoFactor(User $user): void
    {
        $user->saveAppAuthenticationSecret($this->app2fa()->generateSecret());
        // Filament stores recovery codes hashed.
        $user->saveAppAuthenticationRecoveryCodes([Hash::make('recovery-code-1'), Hash::make('recovery-code-2')]);
    }

    private function adminUrl(string $path = ''): string
    {
        $tenant = Tenant::where('slug', 'test-tenant')->firstOrFail();

        return rtrim("/admin/{$tenant->getRouteKey()}/{$path}", '/');
    }

    private function editorIn(Tenant $tenant, array $attributes = []): User
    {
        $user = User::factory()->create(['password' => self::PASSWORD, ...$attributes]);
        $user->tenants()->sync([$tenant->id]);
        $user->update(['default_tenant_id' => $tenant->id]);

        return $user;
    }

    public function test_user_without_required_two_factor_reaches_the_admin(): void
    {
        $user = $this->editorIn($this->createTenant());

        $this->actingAs($user)->get($this->adminUrl())->assertOk();
    }

    public function test_user_of_a_dashboard_that_requires_two_factor_must_set_it_up(): void
    {
        $user = $this->editorIn($this->createTenant(['requires_two_factor_authentication' => true]));

        $this->actingAs($user)->get($this->adminUrl())
            ->assertRedirect(Filament::getSetUpRequiredMultiFactorAuthenticationUrl());
    }

    public function test_requirement_of_another_dashboard_does_not_apply_to_editors(): void
    {
        $this->createTenant(['slug' => 'strict', 'name' => 'Strict', 'requires_two_factor_authentication' => true]);
        $user = $this->editorIn($this->createTenant());

        $this->actingAs($user)->get($this->adminUrl())->assertOk();
    }

    public function test_admins_must_set_it_up_when_any_dashboard_requires_it(): void
    {
        $this->createTenant(['requires_two_factor_authentication' => true]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get($this->adminUrl())
            ->assertRedirect(Filament::getSetUpRequiredMultiFactorAuthenticationUrl());
    }

    public function test_user_with_two_factor_set_up_passes_the_requirement(): void
    {
        $user = $this->editorIn($this->createTenant(['requires_two_factor_authentication' => true]));
        $this->enableTwoFactor($user);

        $this->actingAs($user)->get($this->adminUrl())->assertOk();
    }

    public function test_keycloak_logins_are_exempt_from_the_requirement(): void
    {
        $user = $this->editorIn($this->createTenant(['requires_two_factor_authentication' => true]));

        $this->actingAs($user)
            ->withSession([KeycloakSsoService::SESSION_KEY => true])
            ->get($this->adminUrl())
            ->assertOk();
    }

    public function test_password_alone_does_not_sign_in_a_user_with_two_factor(): void
    {
        $user = $this->editorIn($this->createTenant());
        $this->enableTwoFactor($user);

        Livewire::test(Login::class)
            ->set('data.email', $user->email)
            ->set('data.password', self::PASSWORD)
            ->call('authenticate')
            ->assertSet('userUndertakingMultiFactorAuthentication', fn ($value) => filled($value));

        $this->assertGuest();
    }

    public function test_password_and_app_code_sign_in(): void
    {
        $user = $this->editorIn($this->createTenant());
        $this->enableTwoFactor($user);

        Livewire::test(Login::class)
            ->set('data.email', $user->email)
            ->set('data.password', self::PASSWORD)
            ->call('authenticate')
            ->set('data.multiFactor.app.code', $this->app2fa()->getCurrentCode($user))
            ->call('authenticate')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_app_code_is_rejected(): void
    {
        $user = $this->editorIn($this->createTenant());
        $this->enableTwoFactor($user);

        Livewire::test(Login::class)
            ->set('data.email', $user->email)
            ->set('data.password', self::PASSWORD)
            ->call('authenticate')
            ->set('data.multiFactor.app.code', '000000')
            ->call('authenticate')
            ->assertHasErrors();

        $this->assertGuest();
    }

    public function test_recovery_code_signs_in_once(): void
    {
        $user = $this->editorIn($this->createTenant());
        $this->enableTwoFactor($user);

        Livewire::test(Login::class)
            ->set('data.email', $user->email)
            ->set('data.password', self::PASSWORD)
            ->call('authenticate')
            ->set('data.multiFactor.app.useRecoveryCode', true)
            ->set('data.multiFactor.app.recoveryCode', 'recovery-code-1')
            ->call('authenticate')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($user);
        $this->assertCount(1, $user->fresh()->getAppAuthenticationRecoveryCodes());
    }

    public function test_json_login_refuses_users_with_two_factor(): void
    {
        $user = $this->editorIn($this->createTenant());
        $this->enableTwoFactor($user);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => self::PASSWORD])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->assertGuest();
    }

    public function test_json_login_refuses_users_whose_dashboard_requires_two_factor(): void
    {
        $user = $this->editorIn($this->createTenant(['requires_two_factor_authentication' => true]));

        $this->postJson('/api/login', ['email' => $user->email, 'password' => self::PASSWORD])
            ->assertStatus(422);

        $this->assertGuest();
    }

    public function test_admin_can_reset_a_users_two_factor(): void
    {
        $tenant = $this->createTenant();
        $user = $this->editorIn($tenant);
        $this->enableTwoFactor($user);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);
        $this->setTenantContext($tenant);

        Livewire::test(ListUsers::class)
            ->assertTableActionVisible('resetTwoFactorAuthentication', $user)
            ->callTableAction('resetTwoFactorAuthentication', $user)
            ->assertHasNoTableActionErrors();

        $user->refresh();
        $this->assertNull($user->getAppAuthenticationSecret());
        $this->assertNull($user->getAppAuthenticationRecoveryCodes());
    }

    public function test_reset_action_is_hidden_for_users_without_two_factor(): void
    {
        $tenant = $this->createTenant();
        $user = $this->editorIn($tenant);

        $this->actingAs(User::factory()->admin()->create());
        $this->setTenantContext($tenant);

        Livewire::test(ListUsers::class)
            ->assertTableActionHidden('resetTwoFactorAuthentication', $user);
    }

    public function test_admin_can_require_two_factor_for_a_dashboard(): void
    {
        $tenant = $this->createTenant();
        $this->actingAs(User::factory()->admin()->create());
        $this->setTenantContext($tenant);

        Livewire::test(EditTenantProfile::class)
            ->fillForm(['requires_two_factor_authentication' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($tenant->fresh()->requires_two_factor_authentication);
    }

    public function test_profile_offers_two_factor_set_up(): void
    {
        $user = $this->editorIn($this->createTenant());

        $this->actingAs($user)->get($this->adminUrl('profile'))
            ->assertOk()
            ->assertSee(__('filament.pages.edit_profile.two_factor_authentication'));
    }

    public function test_profile_hides_two_factor_for_keycloak_logins(): void
    {
        $user = $this->editorIn($this->createTenant());

        $this->actingAs($user)
            ->withSession([KeycloakSsoService::SESSION_KEY => true])
            ->get($this->adminUrl('profile'))
            ->assertOk()
            ->assertDontSee(__('filament.pages.edit_profile.two_factor_authentication'));
    }
}
