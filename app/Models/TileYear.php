<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TileYear extends Model
{
    protected $fillable = ['tile_id', 'year'];

    public function tile()
    {
        return $this->belongsTo(Tile::class);
    }

    public function metrics()
    {
        return $this->hasMany(Metric::class)->orderBy('id');
    }

    public function metricValues()
    {
        return $this->hasMany(MetricValue::class);
    }
}
