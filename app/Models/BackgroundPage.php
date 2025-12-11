<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class BackgroundPage extends Model
{

    use HasTranslations;
    use BelongsToTenant;

    public array $translatable = [
        'slug',
        'content'
    ];

    protected $fillable = ['slug', 'content', 'position', 'tile_id', 'tenant_id'];

    /** Cast JSON columns to arrays so Spatie can handle them. */
    protected $casts = [
        'slug'    => 'array',
        'content' => 'array',
    ];

    public function tile()
    {
        return $this->belongsTo(Tile::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
