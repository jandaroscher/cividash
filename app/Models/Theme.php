<?php

namespace App\Models;

use App\Exceptions\ThemeInUseException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Theme extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'settings',
        'parent_theme_id',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    /**
     * Guard against deleting a theme that is still assigned to a dashboard.
     */
    protected static function booted(): void
    {
        static::deleting(function (Theme $theme) {
            if ($theme->tenants()->exists()) {
                throw new ThemeInUseException;
            }
        });
    }

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class, 'theme_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Theme::class, 'parent_theme_id');
    }
}
