<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class MetricDefinition extends Model
{
    use HasTranslations;

    public array $translatable = [
        'label',
        'unit',
    ];

    protected $fillable = [
        'tile_id',
        'metric_key',
        'label',
        'unit',
        'icon',
        'indicator_type',
    ];

    protected $casts = [
        'label' => 'array',
        'unit' => 'array',
    ];

    public function tile()
    {
        return $this->belongsTo(Tile::class);
    }

    public function metricValues()
    {
        return $this->hasMany(MetricValue::class);
    }
}

