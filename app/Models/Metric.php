<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Metric extends Model
{
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

