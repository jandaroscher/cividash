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

    protected $fillable = ['title', 'description', 'icon', 'position'];

    // Many‑to‑Many zu Category
    public function categories()
    {
        return $this->belongsToMany(Category::class);
    }

    // 1:1 zu BackgroundPage
    public function backgroundPage()
    {
        return $this->hasOne(BackgroundPage::class);
    }

    public function tileYears()
    {
        return $this->hasMany(TileYear::class)->orderBy('year');
    }
}

