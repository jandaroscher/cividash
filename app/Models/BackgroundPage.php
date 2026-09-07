<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

class BackgroundPage extends Model
{
    use BelongsToTenant;
    use HasTranslations;

    public array $translatable = [
        'slug',
        'content',
    ];

    protected $fillable = ['slug', 'content', 'position', 'tile_id', 'tenant_id'];

    /** Cast JSON columns to arrays so Spatie can handle them. */
    protected $casts = [
        'slug' => 'array',
        'content' => 'array',
    ];

    /**
     * Get the Tile this background page belongs to.
     *
     * @return BelongsTo The relationship instance linking this background page to its tile.
     */
    public function tile()
    {
        return $this->belongsTo(Tile::class);
    }

    /**
     * Get the tenant that owns this background page.
     *
     * @return BelongsTo The tenant relationship.
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
