<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasDefaultTenant;
use Filament\Models\Contracts\HasName;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasAvatar, HasDefaultTenant, HasName, HasTenants
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
        'is_active',
        'is_admin',
        'default_tenant_id',
        'locale',
        'avatar_path',
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

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_admin' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (User $user) {
            // Admins bypass tenant checks — no need for pivot entries
            if ($user->is_admin) {
                return;
            }

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
     * Get the user's full name.
     */
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * Get the name used by Filament for the user menu and avatar.
     */
    public function getFilamentName(): string
    {
        return $this->full_name;
    }

    /**
     * Determine whether the user may access the given Filament panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return (bool) $this->is_active;
    }

    public function getTenants(Panel $panel): Collection|array
    {
        $tenants = $this->is_admin
            ? Tenant::orderBy('name')->get()
            : $this->tenants()->orderBy('name')->get();

        Tenant::loadAvatarColors($tenants);

        return $tenants;
    }

    /**
     * Resolve the user's default tenant for the given Filament panel,
     * falling back to the first tenant the user can access.
     */
    public function getDefaultTenant(Panel $panel): ?Tenant
    {
        // Priority 1: Match request host against tenant domain
        $host = $this->resolveHostTenant();
        if ($host && $this->canAccessTenant($host)) {
            return $host;
        }

        // Priority 2: User's configured default tenant
        $defaultTenant = $this->defaultTenant;

        if ($defaultTenant && $this->canAccessTenant($defaultTenant)) {
            return $defaultTenant;
        }

        // Priority 3: First tenant the user has access to
        if ($this->is_admin) {
            return Tenant::orderBy('name')->first();
        }

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

    public function canAccessTenant(Model $tenant): bool
    {
        if ($this->is_admin) {
            return true;
        }

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
     */
    public function defaultTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'default_tenant_id');
    }

    /**
     * Get the URL for the user's avatar in Filament.
     */
    public function getFilamentAvatarUrl(): ?string
    {
        if (! $this->avatar_path) {
            return null;
        }

        return Storage::disk('public')->url($this->avatar_path);
    }
}
