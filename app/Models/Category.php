<?php

namespace App\Models;

use App\Models\Concerns\AssignsSequentialPosition;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Translatable\HasTranslations;

class Category extends Model
{
    use AssignsSequentialPosition;
    use BelongsToTenant;
    use HasFactory;
    use HasTranslations;

    protected function positionScopeColumn(): string
    {
        return 'category_group_id';
    }

    public array $translatable = [
        'slug',
    ];

    protected $fillable = [
        'slug',
        'position',
        'icon',
        'color',
        'is_active',
        'category_group_id',
        'key',
        'tenant_id',
    ];

    // cast the JSON -> PHP array
    protected $casts = [
        'slug' => 'array',
        'is_active' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    /**
     * Get the many-to-many relationship for tiles associated with the category.
     *
     * Related Tile models are returned ordered by their `position` attribute.
     *
     * @return BelongsToMany The relation for Tile models ordered by `position`.
     */
    public function tiles()
    {
        return $this->belongsToMany(Tile::class)->orderBy('position');
    }

    /**
     * Get the CategoryGroup this category belongs to.
     *
     * @return BelongsTo BelongsTo relation for the CategoryGroup model.
     */
    public function group()
    {
        return $this->belongsTo(CategoryGroup::class, 'category_group_id');
    }

    /**
     * Get the tenant that owns the category.
     *
     * @return BelongsTo The belongs-to relationship to the Tenant model.
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
