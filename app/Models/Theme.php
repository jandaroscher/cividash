<?php

namespace App\Models;

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

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class, 'theme_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Theme::class, 'parent_theme_id');
    }
}
