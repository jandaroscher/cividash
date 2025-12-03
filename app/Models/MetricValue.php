<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MetricValue extends Model
{
    protected $fillable = [
        'metric_definition_id',
        'tile_year_id',
        'value',
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
}

