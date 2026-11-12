<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardConfigApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_api_sets_and_returns_the_developed_in_place(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        Tenant::create(['name' => 'Demo City', 'slug' => 'demo-city', 'domain' => 'demo-city.example.com']);
        $token = $user->createToken('test-token', ['admin-api'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('http://demo-city.example.com/api/v1/admin/config/dashboard', ['made_in' => 'Demo City'])
            ->assertOk()
            ->assertJsonPath('data.made_in', 'Demo City');

        $this->getJson('http://demo-city.example.com/api/v1/config/dashboard')
            ->assertOk()
            ->assertJsonPath('data.made_in', 'Demo City');
    }
}
