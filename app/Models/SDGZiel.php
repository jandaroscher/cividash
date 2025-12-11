<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class SDGZiel extends Model
{
    use HasTranslations;
    use BelongsToTenant;

    public array $translatable = [
        'title',
        'icon', // Icon is translatable (JSON with de/en)
    ];

    protected $fillable = ['number', 'title', 'icon', 'position', 'tenant_id'];

    protected $casts = [
        'title' => 'array',
        'icon' => 'array', // Icon is stored as JSON array
    ];

    /**
     * Get the table name for the model.
     */
    public function getTable(): string
    {
        return 'sdg_ziele';
    }

    /**
     * Get the tiles associated with this SDG goal.
     *
     * Defines a many-to-many relationship to Tile using the `tile_sdg_ziel` pivot table.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany The relation instance for the associated tiles.
     */
    public function tiles()
    {
        return $this->belongsToMany(Tile::class, 'tile_sdg_ziel', 'sdg_ziel_id', 'tile_id');
    }

    /**
     * Get the tenant that owns this SDGZiel.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo The tenant relationship.
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
