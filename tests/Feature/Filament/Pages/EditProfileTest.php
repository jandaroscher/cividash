<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace Tests\Feature\Filament\Pages;

use App\Filament\Pages\EditProfile;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class EditProfileTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Test Tenant', 'slug' => 'test-tenant']);
        $this->user = User::factory()->create(['password' => Hash::make('old-Password-123')]);
        $this->user->tenants()->attach($this->tenant->id);

        $this->actingAs($this->user);
        Filament::setTenant($this->tenant);
    }

    protected function tearDown(): void
    {
        Filament::setTenant(null);
        parent::tearDown();
    }

    public function test_profile_page_renders_both_forms_with_their_save_buttons(): void
    {
        $this->get("/admin/{$this->tenant->id}/profile")
            ->assertOk()
            ->assertSee(__('filament.pages.edit_profile.save_profile'))
            ->assertSee(__('filament.pages.edit_profile.save_password'));
    }

    public function test_user_can_update_their_name(): void
    {
        Livewire::test(EditProfile::class)
            ->fillForm(['first_name' => 'Renamed'], 'profileForm')
            ->call('updateProfile')
            ->assertHasNoFormErrors(form: 'profileForm');

        $this->assertSame('Renamed', $this->user->fresh()->first_name);
    }

    public function test_user_can_change_their_password(): void
    {
        Livewire::test(EditProfile::class)
            ->fillForm([
                'current_password' => 'old-Password-123',
                'password' => 'new-Password-456!',
                'password_confirmation' => 'new-Password-456!',
            ], 'passwordForm')
            ->call('updatePassword')
            ->assertHasNoFormErrors(form: 'passwordForm');

        $this->assertTrue(Hash::check('new-Password-456!', $this->user->fresh()->password));
    }
}
