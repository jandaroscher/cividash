<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Metric extends Model
{
    use BelongsToTenant;
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
        'tenant_id',
    ];

    /**
     * Get the TileYear that this Metric belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo The belongs-to relationship to the TileYear model.
     */
    public function tileYear()
    {
        return $this->belongsTo(TileYear::class);
    }

    /**
     * Get the tenant that owns the metric.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo The belongs-to relationship for the Tenant model.
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
