<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\Tenant;
use App\Services\RoleService;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        // Skip Filament's automatic tenant attachment (via $tenantOwnershipRelationshipName)
        // since afterCreate() manages all tenant associations manually
        $data['email_verified_at'] = now();

        $record = new (static::getModel())($data);
        $record->save();

        return $record;
    }

    protected function afterCreate(): void
    {
        DB::transaction(function () {
            $record = $this->record;
            $role = $this->data['role'] ?? 'Redakteur';

            if ($role === 'Admin') {
                $record->forceFill(['is_admin' => true])->save();
                // No tenant sync needed — canAccessTenant() + getTenants() handle it
            } else {
                $roleService = app(RoleService::class);
                $assignments = $this->data['dashboard_assignments'] ?? [];
                $record->tenants()->sync($assignments);

                foreach ($assignments as $tenantId) {
                    $tenant = Tenant::find($tenantId);
                    if ($tenant) {
                        $roleService->createDefaultRolesForTenant($tenant);
                        $roleService->assignRoleInTenant($record, 'Redakteur', $tenant);
                    }
                }

                $record->forceFill([
                    'is_admin' => false,
                    'default_tenant_id' => $assignments[0] ?? Filament::getTenant()?->id,
                ])->save();
            }
        });
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
