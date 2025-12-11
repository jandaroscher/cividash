<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Metric extends Model
{
    use HasTranslations;
    use BelongsToTenant;

    public array $translatable = [
        'label',
        'unit',
    ];

    protected $fillable = [
        'tile_year_id',
        'metric_key',
        'label',
        'value',
        'unit',
        'icon',
        'indicator_type',
        'tenant_id',
    ];

    public function tileYear()
    {
        return $this->belongsTo(TileYear::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}

