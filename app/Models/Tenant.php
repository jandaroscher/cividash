<?php

namespace App\Models;

use App\Services\RoleService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'slug',
        'domain',
        'frontend_base_url',
    ];

    /**
     * Bootstrap model events.
     *
     * Creates default roles when a tenant is created.
     */
    protected static function booted(): void
    {
        static::created(function (Tenant $tenant) {
            app(RoleService::class)->createDefaultRolesForTenant($tenant);
        });
    }

    /**
     * Get the users associated with the tenant.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }
}
