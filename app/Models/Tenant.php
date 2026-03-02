<?php

namespace App\Models;

use App\Services\RoleService;
use App\Settings\TenantAwareDatabaseSettingsRepository;
use Filament\Models\Contracts\HasAvatar;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model implements HasAvatar
{
    use HasFactory;

    public ?string $avatarColor = null;

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

    public function tiles(): HasMany
    {
        return $this->hasMany(Tile::class);
    }

    public function categoryGroups(): HasMany
    {
        return $this->hasMany(CategoryGroup::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function getFilamentAvatarUrl(): ?string
    {
        $color = ltrim($this->getAvatarColor(), '#');
        $initials = $this->getAvatarInitials();

        return 'https://ui-avatars.com/api/?name='.urlencode($initials).'&color=ffffff&background='.$color;
    }

    public function getAvatarColor(): string
    {
        return $this->avatarColor ?? static::resolveAvatarColorFromDb($this->id);
    }

    public static function resolveAvatarColorFromDb(int $tenantId): string
    {
        $globalId = TenantAwareDatabaseSettingsRepository::GLOBAL_TENANT_ID;

        $rows = DB::table('settings')
            ->where('group', 'branding')
            ->whereIn('name', ['accent_color', 'primary_color'])
            ->whereIn('tenant_id', [$tenantId, $globalId])
            ->get(['name', 'payload', 'tenant_id']);

        $tenantAccent = null;
        $tenantPrimary = null;
        $globalAccent = null;
        $globalPrimary = null;

        foreach ($rows as $row) {
            $value = json_decode($row->payload, true);
            if (empty($value)) {
                continue;
            }

            if ((int) $row->tenant_id === $tenantId) {
                if ($row->name === 'accent_color') {
                    $tenantAccent = $value;
                } else {
                    $tenantPrimary = $value;
                }
            } else {
                if ($row->name === 'accent_color') {
                    $globalAccent = $value;
                } else {
                    $globalPrimary = $value;
                }
            }
        }

        return $tenantAccent ?? $tenantPrimary ?? $globalAccent ?? $globalPrimary ?? '#0d47a1';
    }

    public static function loadAvatarColors(iterable $tenants): void
    {
        $tenantIds = [];
        $tenantsById = [];

        foreach ($tenants as $tenant) {
            $tenantIds[] = $tenant->id;
            $tenantsById[$tenant->id] = $tenant;
        }

        if (empty($tenantIds)) {
            return;
        }

        $globalId = TenantAwareDatabaseSettingsRepository::GLOBAL_TENANT_ID;

        $rows = DB::table('settings')
            ->where('group', 'branding')
            ->whereIn('name', ['accent_color', 'primary_color'])
            ->whereIn('tenant_id', array_merge($tenantIds, [$globalId]))
            ->get(['name', 'payload', 'tenant_id']);

        $globalAccent = null;
        $globalPrimary = null;
        $tenantColors = [];

        foreach ($rows as $row) {
            $value = json_decode($row->payload, true);
            if (empty($value)) {
                continue;
            }

            $tid = (int) $row->tenant_id;

            if ($tid === $globalId) {
                if ($row->name === 'accent_color') {
                    $globalAccent = $value;
                } else {
                    $globalPrimary = $value;
                }
            } else {
                $tenantColors[$tid][$row->name] = $value;
            }
        }

        $globalFallback = $globalAccent ?? $globalPrimary ?? '#0d47a1';

        foreach ($tenantsById as $id => $tenant) {
            $colors = $tenantColors[$id] ?? [];
            $tenant->avatarColor = $colors['accent_color']
                ?? $colors['primary_color']
                ?? $globalFallback;
        }
    }

    private function getAvatarInitials(): string
    {
        $words = preg_split('/\s+/', trim($this->name));

        if (count($words) >= 2) {
            return mb_strtoupper(mb_substr($words[0], 0, 1).mb_substr($words[1], 0, 1));
        }

        return mb_strtoupper(mb_substr($this->name, 0, 2));
    }
}
