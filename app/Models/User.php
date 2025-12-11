<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasDefaultTenant;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use App\Models\Tenant;

class User extends Authenticatable implements FilamentUser, HasTenants, HasDefaultTenant
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'default_tenant_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (User $user) {
            $tenant = Tenant::firstOrCreate(
                ['slug' => 'default'],
                ['name' => 'Default Tenant']
            );

            $user->tenants()->syncWithoutDetaching($tenant->id);

            if (! $user->default_tenant_id) {
                $user->forceFill(['default_tenant_id' => $tenant->id])->save();
            }
        });
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true; // Allow all authenticated users to access the admin panel
    }

    public function getTenants(Panel $panel): Collection|array
    {
        return $this->tenants()->orderBy('name')->get();
    }

    public function getDefaultTenant(Panel $panel): ?Tenant
    {
        // First, try to get the configured default tenant
        $defaultTenant = null;
        if ($this->relationLoaded('defaultTenant') && $this->defaultTenant) {
            $defaultTenant = $this->defaultTenant;
        } elseif ($this->defaultTenant) {
            $defaultTenant = $this->defaultTenant;
        }

        // Validate that the user has access to the default tenant
        // This prevents unauthorized access if default_tenant_id points to a tenant
        // the user is not linked to (e.g., after tenant access was revoked)
        if ($defaultTenant && $this->canAccessTenant($defaultTenant)) {
            return $defaultTenant;
        }

        // Fallback to first tenant the user has access to
        return $this->tenants()->orderBy('name')->first();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        if (! $this->relationLoaded('tenants')) {
            return $this->tenants()->where('tenants.id', $tenant->getKey())->exists();
        }

        return $this->tenants->contains(fn ($t) => $t->getKey() === $tenant->getKey());
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class)->withTimestamps();
    }

    public function defaultTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'default_tenant_id');
    }
}
