<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class SDGZiel extends Model
{
    use HasTranslations;

    public array $translatable = [
        'title',
        'icon', // Icon is translatable (JSON with de/en)
    ];

    protected $fillable = ['number', 'title', 'icon', 'position'];

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
     * Get the tiles that belong to this SDG goal.
     */
    public function tiles()
    {
        return $this->belongsToMany(Tile::class, 'tile_sdg_ziel', 'sdg_ziel_id', 'tile_id');
    }
}
