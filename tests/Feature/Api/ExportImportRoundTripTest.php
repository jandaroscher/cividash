<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace Tests\Feature\Api;

use App\Models\MetricDefinition;
use App\Models\MetricValue;
use App\Models\Tenant;
use App\Models\Tile;
use App\Models\TimePeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ExportImportRoundTripTest extends TestCase
{
    use RefreshDatabase;

    public function test_json_export_with_yearly_and_quarterly_values_reimports_unchanged_into_another_tenant(): void
    {
        $source = Tenant::where('slug', 'default')->firstOrFail();
        $target = Tenant::create(['name' => 'Demo City', 'slug' => 'demo-city']);

        $tile = Tile::factory()->forTenant($source)->create([
            'title' => ['de' => 'Mobilität', 'en' => 'Mobility'],
            'slug' => ['de' => 'mobilitaet', 'en' => 'mobility'],
        ]);
        $md = MetricDefinition::factory()->forTile($tile)->create([
            'metric_key' => 'co2',
            'label' => ['de' => 'CO₂-Emissionen'],
            'unit' => ['de' => 't'],
        ]);
        $periods = [
            TimePeriod::factory()->forTile($tile)->year(2022)->create(),
            TimePeriod::factory()->forTile($tile)->year(2023)->create(),
            TimePeriod::factory()->forTile($tile)->quarter(2023, 1)->create(),
            TimePeriod::factory()->forTile($tile)->quarter(2023, 2)->create(),
        ];
        foreach ($periods as $i => $tp) {
            MetricValue::factory()->forDefinition($md)->forTimePeriod($tp)->create(['value' => 100 + $i]);
        }

        $export = $this->getJson('/api/v1/tiles/mobilitaet/export?format=json&locale=de');
        $export->assertOk();

        $user = User::factory()->create();
        $user->tenants()->attach($target->id);
        $token = $user->createToken('round-trip', ['admin-api']);
        $token->accessToken->tenant_id = $target->id;
        $token->accessToken->save();

        $this->withHeaders(['Authorization' => "Bearer {$token->plainTextToken}", 'Accept' => 'application/json'])
            ->post('/api/v1/admin/import', [
                'file' => UploadedFile::fake()->createWithContent('export.json', $export->streamedContent()),
                'mode' => 'commit',
            ])
            ->assertOk()
            ->assertJson(['status' => 'success']);

        $snapshot = fn (Tenant $tenant) => [
            'metrics' => MetricDefinition::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->get()
                ->map(fn ($m) => [$m->metric_key, $m->getTranslation('label', 'de'), $m->getTranslation('unit', 'de')])->all(),
            'periods' => TimePeriod::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->orderBy('period_key')->get()
                ->map(fn ($tp) => [$tp->granularity->value, $tp->period_key, $tp->label])->all(),
            'values' => MetricValue::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->with(['timePeriod' => fn ($q) => $q->withoutGlobalScope('tenant')])->get()
                ->map(fn ($mv) => [$mv->timePeriod->period_key, (float) $mv->value])->sortBy(0)->values()->all(),
        ];

        $this->assertSame($snapshot($source), $snapshot($target));
    }
}
