<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class CategoryGroup extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasTranslations;

    protected static function booted(): void
    {
        static::creating(function (self $group) {
            if (is_null($group->position)) {
                DB::transaction(function () use ($group) {
                    $group->position = (int) static::where('tenant_id', $group->tenant_id)->lockForUpdate()->max('position') + 1;
                });
            }
        });
    }

    public array $translatable = [
        'title',
    ];

    protected $fillable = [
        'key',
        'title',
        'position',
        'is_active',
        'tenant_id',
    ];

    protected $casts = [
        'title' => 'array',
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
