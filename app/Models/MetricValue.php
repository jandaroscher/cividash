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
        'tile_year_id',
        'value',
        'is_active',
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

    /**
     * Get the TileYear associated with this metric value.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\TileYear> The belongs-to relationship for the TileYear.
     */
    public function tileYear()
    {
        return $this->belongsTo(TileYear::class);
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
