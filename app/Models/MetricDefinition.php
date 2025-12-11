<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class MetricDefinition extends Model
{
    use HasTranslations;
    use BelongsToTenant;

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
        'tenant_id',
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

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
