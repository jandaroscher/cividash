<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Category extends Model
{
    use HasTranslations;
    use BelongsToTenant;

    public array $translatable = [
        'slug'
    ];

    protected $fillable = ['slug', 'position', 'icon', 'last_synced_at', 'source_hash', 'tenant_id'];

    // cast the JSON -> PHP array
    protected $casts = [
        'slug' => 'array',
        'last_synced_at' => 'datetime',
    ];

    public function tiles()
    {
        return $this->belongsToMany(Tile::class)->orderBy('position');
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
