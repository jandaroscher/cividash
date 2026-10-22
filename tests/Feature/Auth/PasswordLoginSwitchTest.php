<?php

namespace Tests\Feature\Auth;

use App\Filament\Pages\Auth\Login;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PasswordLoginSwitchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        User::factory()->admin()->create(['email' => 'admin@example.com', 'password' => 'correct-horse-battery']);
    }

    private function ssoOnly(): void
    {
        config([
            'integrations.keycloak_sso.enabled' => true,
            'integrations.keycloak_sso.password_login' => false,
        ]);
    }

    public function test_admin_login_page_offers_only_sso_when_password_login_is_off(): void
    {
        $this->ssoOnly();

        $this->get('/admin/login')
            ->assertOk()
            ->assertSee(__('filament.sso.login_with_keycloak'))
            ->assertDontSee('data.password', false);
    }

    public function test_admin_login_page_shows_the_password_form_by_default(): void
    {
        config(['integrations.keycloak_sso.enabled' => true]);

        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('data.password', false);
    }

    public function test_admin_password_login_is_rejected_when_password_login_is_off(): void
    {
        $this->ssoOnly();

        Livewire::test(Login::class)
            ->set('data.email', 'admin@example.com')
            ->set('data.password', 'correct-horse-battery')
            ->call('authenticate')
            ->assertHasErrors();

        $this->assertGuest();
    }

    public function test_admin_password_login_works_by_default_with_sso(): void
    {
        config(['integrations.keycloak_sso.enabled' => true]);

        Livewire::test(Login::class)
            ->set('data.email', 'admin@example.com')
            ->set('data.password', 'correct-horse-battery')
            ->call('authenticate')
            ->assertHasNoErrors();

        $this->assertAuthenticated();
    }

    public function test_password_login_stays_on_while_sso_is_disabled(): void
    {
        // Turning password login off without SSO would lock everyone out.
        config([
            'integrations.keycloak_sso.enabled' => false,
            'integrations.keycloak_sso.password_login' => false,
        ]);

        Livewire::test(Login::class)
            ->set('data.email', 'admin@example.com')
            ->set('data.password', 'correct-horse-battery')
            ->call('authenticate')
            ->assertHasNoErrors();

        $this->assertAuthenticated();
    }

    public function test_api_password_login_is_rejected_when_password_login_is_off(): void
    {
        $this->ssoOnly();

        $this->postJson('/api/login', ['email' => 'admin@example.com', 'password' => 'correct-horse-battery'])
            ->assertForbidden()
            ->assertJson(['message' => __('auth.password_login_disabled')]);

        $this->assertGuest();
    }

    public function test_api_password_login_works_by_default(): void
    {
        $this->postJson('/api/login', ['email' => 'admin@example.com', 'password' => 'correct-horse-battery'])
            ->assertOk();

        $this->assertAuthenticated();
    }
}
