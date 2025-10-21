<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Category extends Model
{
    use HasTranslations;

    public array $translatable = [
        'slug'
    ];

    protected $fillable = ['slug', 'position', 'icon'];

    // cast the JSON -> PHP array
    protected $casts = [
        'slug' => 'array',
    ];

    public function tiles()
    {
        return $this->belongsToMany(Tile::class)->orderBy('position');
    }
}
