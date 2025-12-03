<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Metric extends Model
{
    use HasTranslations;

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
    ];

    public function tileYear()
    {
        return $this->belongsTo(TileYear::class);
    }
}

