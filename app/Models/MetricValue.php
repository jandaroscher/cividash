<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class MetricValue extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'metric_definition_id',
        'tile_year_id',
        'value',
        'tenant_id',
    ];

    protected $casts = [
        'value' => 'decimal:2',
    ];

    public function metricDefinition()
    {
        return $this->belongsTo(MetricDefinition::class);
    }

    public function tileYear()
    {
        return $this->belongsTo(TileYear::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
