<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackgroundPage extends Model
{
    protected $fillable = ['slug', 'content', 'position', 'tile_id'];

    public function tile()
    {
        return $this->belongsTo(Tile::class);
    }
}

