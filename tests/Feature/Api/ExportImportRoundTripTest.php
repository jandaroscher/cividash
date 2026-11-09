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
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ExportImportRoundTripTest extends TestCase
{
    use RefreshDatabase;

    public function test_json_export_of_yearly_and_quarterly_tiles_reimports_unchanged_into_another_tenant(): void
    {
        $source = Tenant::where('slug', 'default')->firstOrFail();
        $target = Tenant::create(['name' => 'Demo City', 'slug' => 'demo-city']);

        $yearly = Tile::factory()->forTenant($source)->create([
            'title' => ['de' => 'Mobilität', 'en' => 'Mobility'],
            'slug' => ['de' => 'mobilitaet', 'en' => 'mobility'],
            'description' => ['de' => '<p>Rad <strong>und</strong> Bus</p>'],
            'hint' => ['de' => '<p>Quelle: <a href="https://example.org">Amt</a></p>'],
            'time_granularity' => 'year',
        ]);
        $quarterly = Tile::factory()->forTenant($source)->create([
            'title' => ['de' => 'Energie'],
            'slug' => ['de' => 'energie'],
            'time_granularity' => 'quarter',
        ]);
        $this->seedValues($yearly, 'co2', [[2022, null], [2023, null]]);
        $this->seedValues($quarterly, 'kwh', [[2023, 1], [2023, 2]]);

        $export = $this->getJson('/api/v1/exports/catalog?format=json&locale=de');
        $export->assertOk();

        $this->import($target, $export->streamedContent())->assertOk()->assertJson(['status' => 'success']);
        $this->assertSame($this->snapshot($source), $this->snapshot($target));

        // Importing the same export again changes nothing.
        $second = $this->import($target, $export->streamedContent())->assertOk();
        foreach ($second->json('diff') as $entity => $counts) {
            $this->assertSame(0, $counts['create'] + $counts['update'], "{$entity} changed on re-import");
        }
        $this->assertSame($this->snapshot($source), $this->snapshot($target));
    }

    /**
     * @param  list<array{0: int, 1: int|null}>  $periods  [year, quarter|null]
     */
    private function seedValues(Tile $tile, string $metricKey, array $periods): void
    {
        $md = MetricDefinition::factory()->forTile($tile)->create([
            'metric_key' => $metricKey,
            'label' => ['de' => "Label {$metricKey}"],
            'unit' => ['de' => 't'],
        ]);
        foreach ($periods as $i => [$year, $quarter]) {
            $factory = TimePeriod::factory()->forTile($tile);
            $tp = ($quarter === null ? $factory->year($year) : $factory->quarter($year, $quarter))->create();
            MetricValue::factory()->forDefinition($md)->forTimePeriod($tp)->create(['value' => 100 + $i]);
        }
    }

    private function import(Tenant $target, string $content): TestResponse
    {
        $user = User::factory()->create();
        $user->tenants()->attach($target->id);
        $token = $user->createToken('round-trip', ['admin-api']);
        $token->accessToken->tenant_id = $target->id;
        $token->accessToken->save();

        return $this->withHeaders(['Authorization' => "Bearer {$token->plainTextToken}", 'Accept' => 'application/json'])
            ->post('/api/v1/admin/import', [
                'file' => UploadedFile::fake()->createWithContent('export.json', $content),
                'mode' => 'commit',
            ]);
    }

    private function snapshot(Tenant $tenant): array
    {
        $scoped = fn (string $model) => $model::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id);
        $tiles = $scoped(Tile::class)->get()->keyBy('id');

        return [
            'tiles' => $tiles->map(fn ($t) => [
                $t->getTranslation('slug', 'de'), $t->getTranslation('title', 'de'),
                $t->getTranslation('description', 'de'), $t->getTranslation('hint', 'de'), $t->time_granularity,
            ])->sortBy(0)->values()->all(),
            'metrics' => $scoped(MetricDefinition::class)->orderBy('metric_key')->get()
                ->map(fn ($m) => [$tiles[$m->tile_id]->getTranslation('slug', 'de'), $m->metric_key, $m->getTranslation('label', 'de'), $m->getTranslation('unit', 'de')])->all(),
            'periods' => $scoped(TimePeriod::class)->orderBy('period_key')->get()
                ->map(fn ($tp) => [$tiles[$tp->tile_id]->getTranslation('slug', 'de'), $tp->granularity->value, $tp->period_key, $tp->label])->all(),
            'values' => $scoped(MetricValue::class)->with(['timePeriod' => fn ($q) => $q->withoutGlobalScope('tenant')])->get()
                ->map(fn ($mv) => [$mv->timePeriod->period_key, (float) $mv->value])->sortBy(0)->values()->all(),
        ];
    }
}
