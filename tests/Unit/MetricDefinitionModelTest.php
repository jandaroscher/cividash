<?php

namespace Tests\Unit;

use App\Models\MetricDefinition;
use App\Models\MetricValue;
use App\Models\Tenant;
use App\Models\Tile;
use App\Models\TimePeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetricDefinitionModelTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::where('slug', 'default')->first();
    }

    public function test_belongs_to_tile(): void
    {
        $tile = Tile::factory()->forTenant($this->tenant)->create();

        $definition = MetricDefinition::factory()->forTile($tile)->create();

        $this->assertNotNull($definition->tile);
        $this->assertEquals($tile->id, $definition->tile->id);
    }

    public function test_belongs_to_tenant(): void
    {
        $tile = Tile::factory()->forTenant($this->tenant)->create();

        $definition = MetricDefinition::factory()->forTile($tile)->create();

        $this->assertNotNull($definition->tenant);
        $this->assertEquals($this->tenant->id, $definition->tenant->id);
    }

    public function test_translatable_fields(): void
    {
        $tile = Tile::factory()->forTenant($this->tenant)->create();

        $definition = MetricDefinition::factory()->forTile($tile)->create([
            'label' => ['de' => 'CO2-Emissionen', 'en' => 'CO2 Emissions'],
            'unit' => ['de' => 't CO2', 'en' => 't CO2'],
        ]);

        $this->assertEquals('CO2-Emissionen', $definition->getTranslation('label', 'de'));
        $this->assertEquals('CO2 Emissions', $definition->getTranslation('label', 'en'));
        $this->assertEquals('t CO2', $definition->getTranslation('unit', 'de'));
        $this->assertEquals('t CO2', $definition->getTranslation('unit', 'en'));
    }

    public function test_has_many_metric_values(): void
    {
        $tile = Tile::factory()->forTenant($this->tenant)->create();
        $timePeriod2023 = TimePeriod::factory()->forTile($tile)->year(2023)->create();
        $timePeriod2024 = TimePeriod::factory()->forTile($tile)->year(2024)->create();
        $definition = MetricDefinition::factory()->forTile($tile)->create();

        MetricValue::factory()
            ->forDefinition($definition)
            ->forTimePeriod($timePeriod2023)
            ->create(['value' => 42.50]);

        MetricValue::factory()
            ->forDefinition($definition)
            ->forTimePeriod($timePeriod2024)
            ->create(['value' => 99.00]);

        $definition->refresh();

        $this->assertCount(2, $definition->metricValues);
    }

    public function test_belongs_to_tenant_scope(): void
    {
        $otherTenant = Tenant::create(['name' => 'Other', 'slug' => 'other']);

        $tile = Tile::factory()->forTenant($this->tenant)->create();
        $otherTile = Tile::factory()->forTenant($otherTenant)->create();

        MetricDefinition::factory()->forTile($tile)->create([
            'metric_key' => 'default-metric',
        ]);

        MetricDefinition::factory()->forTile($otherTile)->create([
            'metric_key' => 'other-metric',
        ]);

        // Global scope should filter to default tenant
        $definitions = MetricDefinition::all();

        $this->assertTrue($definitions->contains(fn ($d) => $d->metric_key === 'default-metric'));
        $this->assertFalse($definitions->contains(fn ($d) => $d->metric_key === 'other-metric'));
    }

    public function test_set_translatable_label_updates_correctly(): void
    {
        $tile = Tile::factory()->forTenant($this->tenant)->create();

        $definition = MetricDefinition::factory()->forTile($tile)->create([
            'label' => ['de' => 'Original', 'en' => 'Original'],
        ]);

        $definition->setTranslation('label', 'de', 'Aktualisiert');
        $definition->save();
        $definition->refresh();

        $this->assertEquals('Aktualisiert', $definition->getTranslation('label', 'de'));
        $this->assertEquals('Original', $definition->getTranslation('label', 'en'));
    }
}
