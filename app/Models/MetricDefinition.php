<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class MetricDefinition extends Model
{
    use BelongsToTenant;
    use HasFactory;
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
        'is_active',
        'sort_order',
        'external_source',
        'external_id',
        'last_synced_at',
        'source_hash',
        'tenant_id',
    ];

    protected $casts = [
        'label' => 'array',
        'unit' => 'array',
        'is_active' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    public function tile()
    {
        return $this->belongsTo(Tile::class);
    }

    /**
     * Get the metric values associated with this metric definition.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany| \App\Models\MetricValue[] A HasMany relation containing MetricValue models linked to this MetricDefinition.
     */
    public function metricValues()
    {
        return $this->hasMany(MetricValue::class)->orderBy('sort_order');
    }

    /**
     * Get the tenant that owns this metric definition.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo The belongs-to relationship to the Tenant model.
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
