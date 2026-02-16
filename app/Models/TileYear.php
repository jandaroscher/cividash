<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TileYear extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = ['tile_id', 'year', 'tenant_id'];

    /**
     * Get the Tile that this TileYear belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo The relationship to the Tile model.
     */
    public function tile()
    {
        return $this->belongsTo(Tile::class);
    }

    public function metrics()
    {
        return $this->hasMany(Metric::class)->orderBy('id');
    }

    /**
     * Get the metric values associated with this tile year.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany HasMany relation for MetricValue models related to this TileYear.
     */
    public function metricValues()
    {
        return $this->hasMany(MetricValue::class);
    }

    /**
     * Get the tenant that owns this TileYear.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo The belongs-to relationship to the Tenant model.
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
