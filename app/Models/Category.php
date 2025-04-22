<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['slug', 'position'];

    public function tiles()
    {
        return $this->belongsToMany(Tile::class)->orderBy('position');
    }
}
