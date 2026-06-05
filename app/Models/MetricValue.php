<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MetricValue extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'metric_definition_id',
        'time_period_id',
        'value',
        'is_active',
        'sort_order',
        'tenant_id',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function metricDefinition()
    {
        return $this->belongsTo(MetricDefinition::class);
    }

    public function timePeriod()
    {
        return $this->belongsTo(TimePeriod::class);
    }

    /**
     * Get the tenant that owns this metric value.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo The tenant relationship.
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
