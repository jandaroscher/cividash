<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasDefaultTenant;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser, HasDefaultTenant, HasTenants
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

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
     * Define attribute casting rules for the model.
     *
     * @return array<string, string> Mapping of attribute names to their cast types (e.g., 'datetime', 'boolean', 'hashed').
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'admin_api_enabled' => 'boolean',
        ];
    }

    /**
     * Bootstraps the default tenant and user-tenant association on user creation.
     *
     * On creation of a User, ensures a tenant with slug "default" exists, attaches that tenant
     * to the new user without detaching existing tenant relations, and sets the user's
     * `default_tenant_id` to that tenant if it is not already set.
     */
    protected static function booted(): void
    {
        static::created(function (User $user) {
            $tenant = Tenant::firstOrCreate(
                ['slug' => 'default'],
                ['name' => 'Default Dashboard']
            );

            $user->tenants()->syncWithoutDetaching($tenant->id);

            if (! $user->default_tenant_id) {
                $user->forceFill(['default_tenant_id' => $tenant->id])->save();
            }
        });
    }

    /**
     * Determine whether the user may access the given Filament panel.
     *
     * This implementation permits access for all authenticated users.
     *
     * @param  Panel  $panel  The Filament panel to check access for.
     * @return bool `true` if the user may access the panel, `false` otherwise.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return true; // Allow all authenticated users to access the admin panel
    }

    /**
     * Provide the user's tenants ordered by name for the given Filament panel.
     *
     * @param  Panel  $panel  The Filament panel requesting the tenant list.
     * @return Collection|array A collection or array of Tenant models belonging to the user, ordered by the tenants' `name`.
     */
    public function getTenants(Panel $panel): Collection|array
    {
        return $this->tenants()->orderBy('name')->get();
    }

    /**
     * Resolve the user's default tenant for the given Filament panel, falling back to the first tenant the user can access.
     *
     * @param  Panel  $panel  The Filament panel context used for tenant resolution.
     * @return Tenant|null The user's configured default Tenant if the user has access to it; otherwise the first accessible Tenant ordered by name, or `null` if the user has no tenants.
     */
    public function getDefaultTenant(Panel $panel): ?Tenant
    {
        // Priority 1: Match request host against tenant domain
        $host = $this->resolveHostTenant();
        if ($host && $this->canAccessTenant($host)) {
            return $host;
        }

        // Priority 2: User's configured default tenant
        $defaultTenant = null;
        if ($this->relationLoaded('defaultTenant') && $this->defaultTenant) {
            $defaultTenant = $this->defaultTenant;
        } elseif ($this->defaultTenant) {
            $defaultTenant = $this->defaultTenant;
        }

        if ($defaultTenant && $this->canAccessTenant($defaultTenant)) {
            return $defaultTenant;
        }

        // Priority 3: First tenant the user has access to
        return $this->tenants()->orderBy('name')->first();
    }

    /**
     * Resolve a tenant by matching the current request host against tenant domains.
     */
    protected function resolveHostTenant(): ?Tenant
    {
        $request = request();
        $host = $request->getHost();
        if (! $host) {
            return null;
        }

        $host = strtolower($host);
        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        return Tenant::where('domain', $host)->first();
    }

    /**
     * Determine whether the user can access the given tenant.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $tenant  The tenant model to check access for.
     * @return bool `true` if the user has access to the tenant, `false` otherwise.
     */
    public function canAccessTenant(Model $tenant): bool
    {
        if (! $this->relationLoaded('tenants')) {
            return $this->tenants()->where('tenants.id', $tenant->getKey())->exists();
        }

        return $this->tenants->contains(fn ($t) => $t->getKey() === $tenant->getKey());
    }

    /**
     * Get the many-to-many relationship for tenants associated with the user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\App\Models\Tenant>
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class)->withTimestamps();
    }

    /**
     * Get the belongs-to relationship for the user's default tenant.
     *
     * The relation uses the `default_tenant_id` foreign key on the users table.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo The user's default Tenant relation.
     */
    public function defaultTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'default_tenant_id');
    }
}
