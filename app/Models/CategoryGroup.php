<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class CategoryGroup extends Model
{
    use HasTranslations;
    use BelongsToTenant;

    public array $translatable = [
        'title',
    ];

    protected $fillable = [
        'key',
        'title',
        'position',
        'is_filterable',
        'is_color_source',
        'is_active',
        'selection_type',
        'tenant_id',
    ];

    protected $casts = [
        'title' => 'array',
        'is_filterable' => 'boolean',
        'is_color_source' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get child categories that belong to this category group.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany A HasMany relation for the Category models related to this group.
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /**
     * Get the tenant that owns this category group.
     *
     * @return BelongsTo The tenant this category group belongs to.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}