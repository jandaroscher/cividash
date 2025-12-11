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

    /**
     * Get the many-to-many relationship for tiles associated with the category.
     *
     * Related Tile models are returned ordered by their `position` attribute.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany The relation for Tile models ordered by `position`.
     */
    public function tiles()
    {
        return $this->belongsToMany(Tile::class)->orderBy('position');
    }

    /**
     * Get the tenant that owns the category.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo The belongs-to relationship to the Tenant model.
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
