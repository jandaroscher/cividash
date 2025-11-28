<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Tile extends Model
{
    use HasTranslations;

    // List of JSON columns to translate:
    public array $translatable = [
        'title',
        'description',
    ];

    protected $fillable = ['title', 'description', 'icon', 'position', 'background_blocks'];

    protected $casts = [
        'background_blocks' => 'array',
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
}
