<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class BackgroundPage extends Model
{

    use HasTranslations;

    public array $translatable = [
        'slug',
        'content'
    ];

    protected $fillable = ['slug', 'content', 'position', 'tile_id'];

    /** Cast JSON columns to arrays so Spatie can handle them. */
    protected $casts = [
        'slug'    => 'array',
        'content' => 'array',
    ];

    public function tile()
    {
        return $this->belongsTo(Tile::class);
    }
}
