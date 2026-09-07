<?php

namespace App\Settings;

use App\Models\Concerns\ResolvesCurrentTenant;
use App\Models\Tenant;
use Illuminate\Support\Facades\Schema;
use Spatie\LaravelSettings\SettingsRepositories\DatabaseSettingsRepository;

class TenantAwareDatabaseSettingsRepository extends DatabaseSettingsRepository
{
    use ResolvesCurrentTenant;

    /**
     * Sentinel value representing global (no tenant) settings rows.
     * Using 0 instead of NULL ensures the composite unique index
     * (group, name, tenant_id) properly prevents duplicate global rows.
     */
    public const GLOBAL_TENANT_ID = 0;

    /**
     * Check if the tenant_id column exists on the settings table.
     * Returns false during early migrations before our migration runs.
     * Not cached because the column may be added mid-process during migrations.
     */
    private function hasTenantIdColumn(): bool
    {
        try {
            return Schema::hasColumn('settings', 'tenant_id');
        } catch (\Throwable) {
            return false;
        }
    }

    protected function getCurrentTenantId(): int
    {
        return $this->resolveActiveTenant()?->id ?? self::GLOBAL_TENANT_ID;
    }

    /**
     * Resolve the active tenant, honouring the same guards as getCurrentTenantId()
     * (no tenant_id column yet, or app not booted). Reused so the theme layer looks
     * at exactly the tenant the rest of this repository is scoped to.
     */
    private function resolveActiveTenant(): ?Tenant
    {
        if (! $this->hasTenantIdColumn()) {
            return null;
        }

        // Guard: don't resolve tenant during service provider boot.
        // Settings may be loaded before Filament is fully initialized (e.g. AdminPanelProvider
        // reads GeneralSettings for the favicon). Calling the Filament facade at that point
        // triggers FilamentManager construction which re-resolves PanelRegistry mid-boot,
        // corrupting the panel state and causing null tenant in views.
        if (! app()->isBooted()) {
            return null;
        }

        return static::resolveTenant()?->loadMissing('theme');
    }

    /**
     * Deep-merge array payloads (e.g. slider_colors) so an overriding layer can
     * override a single sub-key without losing the other base sub-keys. List
     * payloads (e.g. typography_font_weights) are replaced wholesale instead -
     * recursive-merging by index would leave stale tail elements from the
     * base list dangling behind a shorter overriding list.
     *
     * Shared by every settings layer (theme-over-global, tenant-over-merged)
     * so all layers merge with identical semantics.
     */
    private function deepMergeSettings(array $base, array $over): array
    {
        $merged = $base;
        foreach ($over as $name => $value) {
            $mergeable = is_array($value) && isset($base[$name]) && is_array($base[$name])
                && ! array_is_list($base[$name])
                && ! array_is_list($value);
            $merged[$name] = $mergeable
                ? $this->deepMergeSettings($base[$name], $value)
                : $value;
        }

        return $merged;
    }

    /**
     * Load global defaults, overlay the active tenant's theme (if any), then
     * overlay tenant-specific values on top: global -> theme -> tenant.
     */
    public function getPropertiesInGroup(string $group): array
    {
        if (! $this->hasTenantIdColumn()) {
            return parent::getPropertiesInGroup($group);
        }

        $tenant = $this->resolveActiveTenant();
        $tenantId = $tenant?->id ?? self::GLOBAL_TENANT_ID;

        // Start with global defaults (tenant_id = 0)
        $globals = $this->getBuilder()
            ->where('group', $group)
            ->where('tenant_id', self::GLOBAL_TENANT_ID)
            ->get(['name', 'payload'])
            ->mapWithKeys(fn (object $row) => [$row->name => $this->decode($row->payload, true)])
            ->toArray();

        if ($tenantId === self::GLOBAL_TENANT_ID) {
            return $globals;
        }

        // Overlay the tenant's theme, if it has one that defines this group.
        $theme = $tenant->theme;
        $themeSettings = is_array($theme?->settings) ? ($theme->settings[$group] ?? null) : null;
        $withTheme = is_array($themeSettings)
            ? $this->deepMergeSettings($globals, $themeSettings)
            : $globals;

        // Overlay tenant-specific values
        $tenantValues = $this->getBuilder()
            ->where('group', $group)
            ->where('tenant_id', $tenantId)
            ->get(['name', 'payload'])
            ->mapWithKeys(fn (object $row) => [$row->name => $this->decode($row->payload, true)])
            ->toArray();

        return $this->deepMergeSettings($withTheme, $tenantValues);
    }

    /**
     * Check tenant-specific first, then global.
     */
    public function checkIfPropertyExists(string $group, string $name): bool
    {
        if (! $this->hasTenantIdColumn()) {
            return parent::checkIfPropertyExists($group, $name);
        }

        $tenantId = $this->getCurrentTenantId();

        if ($tenantId !== self::GLOBAL_TENANT_ID) {
            $exists = $this->getBuilder()
                ->where('group', $group)
                ->where('name', $name)
                ->where('tenant_id', $tenantId)
                ->exists();

            if ($exists) {
                return true;
            }
        }

        // Fall back to global
        return $this->getBuilder()
            ->where('group', $group)
            ->where('name', $name)
            ->where('tenant_id', self::GLOBAL_TENANT_ID)
            ->exists();
    }

    /**
     * Return tenant-specific payload if exists, else global.
     */
    public function getPropertyPayload(string $group, string $name)
    {
        if (! $this->hasTenantIdColumn()) {
            return parent::getPropertyPayload($group, $name);
        }

        $tenantId = $this->getCurrentTenantId();

        if ($tenantId !== self::GLOBAL_TENANT_ID) {
            $setting = $this->getBuilder()
                ->where('group', $group)
                ->where('name', $name)
                ->where('tenant_id', $tenantId)
                ->first('payload');

            if ($setting) {
                return $this->decode($setting->toArray()['payload']);
            }
        }

        // Fall back to global
        $setting = $this->getBuilder()
            ->where('group', $group)
            ->where('name', $name)
            ->where('tenant_id', self::GLOBAL_TENANT_ID)
            ->first('payload');

        if ($setting) {
            return $this->decode($setting->toArray()['payload']);
        }

        return null;
    }

    /**
     * Create with current tenant_id (0 for global context).
     */
    public function createProperty(string $group, string $name, $payload, bool $locked = false): void
    {
        if (! $this->hasTenantIdColumn()) {
            parent::createProperty($group, $name, $payload, $locked);

            return;
        }

        $this->getBuilder()->create([
            'group' => $group,
            'name' => $name,
            'payload' => $this->encode($payload),
            'locked' => $locked,
            'tenant_id' => $this->getCurrentTenantId(),
        ]);
    }

    /**
     * Upsert with tenant-aware unique key [group, name, tenant_id].
     */
    public function updatePropertiesPayload(string $group, array $properties): void
    {
        if (! $this->hasTenantIdColumn()) {
            parent::updatePropertiesPayload($group, $properties);

            return;
        }

        $tenantId = $this->getCurrentTenantId();

        $propertiesInBatch = collect($properties)->map(fn ($payload, $name) => [
            'group' => $group,
            'name' => $name,
            'payload' => $this->encode($payload),
            'tenant_id' => $tenantId,
        ])->values()->toArray();

        $this->getBuilder()
            ->upsert($propertiesInBatch, ['group', 'name', 'tenant_id'], ['payload']);
    }

    /**
     * Delete a settings property scoped to the current context.
     *
     * When a tenant is resolved, deletes the tenant-specific row
     * (where tenant_id = resolved tenant ID). When no tenant context
     * exists, deletes the global row (where tenant_id = GLOBAL_TENANT_ID).
     *
     * Falls back to the parent (unscoped) implementation when the
     * settings table has no tenant_id column.
     */
    public function deleteProperty(string $group, string $name): void
    {
        if (! $this->hasTenantIdColumn()) {
            parent::deleteProperty($group, $name);

            return;
        }

        $this->getBuilder()
            ->where('group', $group)
            ->where('name', $name)
            ->where('tenant_id', $this->getCurrentTenantId())
            ->delete();
    }

    /**
     * Lock properties scoped by tenant.
     */
    public function lockProperties(string $group, array $properties): void
    {
        if (! $this->hasTenantIdColumn()) {
            parent::lockProperties($group, $properties);

            return;
        }

        $this->scopedPropertyQuery($group, $properties)
            ->update(['locked' => true]);
    }

    /**
     * Unlock properties scoped by tenant.
     */
    public function unlockProperties(string $group, array $properties): void
    {
        if (! $this->hasTenantIdColumn()) {
            parent::unlockProperties($group, $properties);

            return;
        }

        $this->scopedPropertyQuery($group, $properties)
            ->update(['locked' => false]);
    }

    /**
     * Get locked properties scoped by tenant.
     */
    public function getLockedProperties(string $group): array
    {
        if (! $this->hasTenantIdColumn()) {
            return parent::getLockedProperties($group);
        }

        return $this->getBuilder()
            ->where('group', $group)
            ->where('locked', true)
            ->where('tenant_id', $this->getCurrentTenantId())
            ->pluck('name')
            ->toArray();
    }

    private function scopedPropertyQuery(string $group, array $properties)
    {
        return $this->getBuilder()
            ->where('group', $group)
            ->whereIn('name', $properties)
            ->where('tenant_id', $this->getCurrentTenantId());
    }
}
