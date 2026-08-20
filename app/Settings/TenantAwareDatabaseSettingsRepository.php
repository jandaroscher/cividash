<?php

namespace App\Settings;

use App\Models\Concerns\ResolvesCurrentTenant;
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
        if (! $this->hasTenantIdColumn()) {
            return self::GLOBAL_TENANT_ID;
        }

        // Guard: don't resolve tenant during service provider boot.
        // Settings may be loaded before Filament is fully initialized (e.g. AdminPanelProvider
        // reads GeneralSettings for the favicon). Calling the Filament facade at that point
        // triggers FilamentManager construction which re-resolves PanelRegistry mid-boot,
        // corrupting the panel state and causing null tenant in views.
        if (! app()->isBooted()) {
            return self::GLOBAL_TENANT_ID;
        }

        return static::resolveTenant()?->id ?? self::GLOBAL_TENANT_ID;
    }

    /**
     * Load global defaults, then overlay tenant-specific values on top.
     */
    public function getPropertiesInGroup(string $group): array
    {
        if (! $this->hasTenantIdColumn()) {
            return parent::getPropertiesInGroup($group);
        }

        $tenantId = $this->getCurrentTenantId();

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

        // Overlay tenant-specific values
        $tenantValues = $this->getBuilder()
            ->where('group', $group)
            ->where('tenant_id', $tenantId)
            ->get(['name', 'payload'])
            ->mapWithKeys(fn (object $row) => [$row->name => $this->decode($row->payload, true)])
            ->toArray();

        // Deep-merge array payloads (e.g. slider_colors) so a tenant can override
        // a single sub-key without losing the other global sub-keys. List
        // payloads (e.g. typography_font_weights) are replaced wholesale instead -
        // recursive-merging by index would leave stale tail elements from the
        // global list dangling behind a shorter tenant list.
        $merged = $globals;
        foreach ($tenantValues as $name => $value) {
            $mergeable = is_array($value) && isset($globals[$name]) && is_array($globals[$name])
                && ! array_is_list($value);
            $merged[$name] = $mergeable
                ? array_replace_recursive($globals[$name], $value)
                : $value;
        }

        return $merged;
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
