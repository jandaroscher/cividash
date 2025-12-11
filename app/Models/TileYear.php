<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class TileYear extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tile_id', 'year', 'tenant_id'];

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

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
