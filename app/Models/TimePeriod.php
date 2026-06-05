<?php

namespace App\Models;

use App\Enums\TimeGranularity;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimePeriod extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'tile_id',
        'granularity',
        'period_key',
        'label',
        'sort',
        'tenant_id',
    ];

    protected $casts = [
        'granularity' => TimeGranularity::class,
    ];

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

    /**
     * Scope to filter by granularity type (e.g. year vs. quarter).
     */
    public function scopeForGranularity(Builder $query, string|TimeGranularity $granularity): Builder
    {
        $value = $granularity instanceof TimeGranularity ? $granularity->value : $granularity;

        return $query->where('granularity', $value);
    }
}
