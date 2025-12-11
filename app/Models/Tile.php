<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Tile extends Model
{
    use HasTranslations;
    use BelongsToTenant;

    // List of JSON columns to translate:
    public array $translatable = [
        'title',
        'description',
    ];

    protected $fillable = ['title', 'description', 'icon', 'position', 'background_blocks', 'last_synced_at', 'source_hash', 'handlungsdimension_id', 'tenant_id'];

    protected $casts = [
        'title' => 'array',
        'description' => 'array',
        'background_blocks' => 'array',
        'last_synced_at' => 'datetime',
    ];

    /**
     * Defines the many-to-many relationship between the tile and Category models.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany Relationship instance for the associated Category models.
     */
    public function categories()
    {
        return $this->belongsToMany(Category::class);
    }

    /**
     * Get the handlungsfelder (categories) that belong to this tile.
     * Alias for categories() for consistency with new naming.
     */
    public function handlungsfelder()
    {
        return $this->belongsToMany(Category::class);
    }

    /**
     * Get the SDG goals that belong to this tile.
     */
    public function sdgZiele()
    {
        return $this->belongsToMany(SDGZiel::class, 'tile_sdg_ziel', 'tile_id', 'sdg_ziel_id');
    }

    /**
     * Get the handlungsdimension that belongs to this tile.
     */
    public function handlungsdimension()
    {
        return $this->belongsTo(Handlungsdimension::class);
    }

    // Background blocks are now embedded directly in the tile model
    /**
     * Define a one-to-many relationship to TileYear models ordered by the `year` field ascending.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany Collection of TileYear models ordered by year.
     */

    public function tileYears()
    {
        return $this->hasMany(TileYear::class)->orderBy('year');
    }

    /**
     * Get the has-many relationship for metric definitions belonging to this tile.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany A has-many relationship to MetricDefinition models.
     */
    public function metricDefinitions()
    {
        return $this->hasMany(MetricDefinition::class);
    }

    /**
     * Get the tenant that owns the tile.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo The tenant that owns the tile.
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
