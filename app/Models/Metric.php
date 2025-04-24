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
        'label',
        'value',
        'unit',
        'icon',
    ];

    public function tileYear()
    {
        return $this->belongsTo(TileYear::class);
    }
}

