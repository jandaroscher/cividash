<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tile extends Model
{
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

