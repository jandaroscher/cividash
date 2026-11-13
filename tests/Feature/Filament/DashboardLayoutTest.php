<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace Tests\Feature\Filament;

use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardLayoutTest extends TestCase
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

        $this->actingAs($this->user);
        Filament::auth()->login($this->user);
        Filament::setTenant($this->tenant);
    }

    public function test_dashboard_renders_stats_row_above_two_column_grid(): void
    {
        $response = $this->followingRedirects()->get('/admin');

        $response->assertStatus(200);
        $response->assertSeeInOrder([
            'data-testid="dashboard-stats" class="grid grid-cols-1 gap-6 md:grid-cols-3"',
            'data-testid="dashboard-grid" class="grid grid-cols-1 gap-6 md:grid-cols-2"',
        ], false);
    }
}
