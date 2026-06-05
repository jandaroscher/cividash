<?php

namespace Database\Factories;

use App\Models\MetricDefinition;
use App\Models\MetricValue;
use App\Models\Tenant;
use App\Models\TimePeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MetricValue>
 */
class MetricValueFactory extends Factory
{
    protected $model = MetricValue::class;

    public function definition(): array
    {
        return [
            'metric_definition_id' => MetricDefinition::factory(),
            'time_period_id' => TimePeriod::factory(),
            'value' => fake()->randomFloat(2, 0, 1000),
            'is_active' => true,
            'sort_order' => 0,
            'tenant_id' => Tenant::factory(),
        ];
    }

    public function forDefinition(MetricDefinition $definition): static
    {
        return $this->state(fn (array $attributes) => [
            'metric_definition_id' => $definition->id,
            'tenant_id' => $definition->tenant_id,
        ]);
    }

    public function forTimePeriod(TimePeriod $timePeriod): static
    {
        return $this->state(fn (array $attributes) => [
            'time_period_id' => $timePeriod->id,
            'tenant_id' => $timePeriod->tenant_id,
        ]);
    }
}
