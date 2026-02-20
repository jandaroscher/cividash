<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RoleService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->before(function (Actions\DeleteAction $action) {
                    if ($this->record->is_admin && ! User::where('is_admin', true)->where('is_active', true)->where('id', '!=', $this->record->id)->exists()) {
                        Notification::make()
                            ->title(__('filament.resources.user.messages.cannot_delete_last_admin'))
                            ->danger()
                            ->send();

                        $action->cancel();
                    }
                }),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->record;

        $data['role'] = $record->is_admin ? 'Admin' : 'Redakteur';
        if (! $record->is_admin) {
            $data['dashboard_assignments'] = $record->tenants->pluck('id')->toArray();
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $record = $this->record;
        $role = $this->data['role'] ?? 'Redakteur';
        $roleService = app(RoleService::class);

        // Last-admin protection
        if ($record->is_admin && $role !== 'Admin') {
            if (! User::where('is_admin', true)->where('is_active', true)->where('id', '!=', $record->id)->exists()) {
                Notification::make()
                    ->title(__('filament.resources.user.messages.cannot_demote_last_admin'))
                    ->danger()
                    ->send();

                return;
            }
        }

        if ($role === 'Admin') {
            // Remove Redakteur roles + tenant associations
            foreach ($record->tenants as $tenant) {
                $roleService->removeAllRolesInTenant($record, $tenant);
            }
            $record->tenants()->detach();
            $record->forceFill(['is_admin' => true])->save();
        } else {
            $assignments = $this->data['dashboard_assignments'] ?? [];

            // Detach unassigned tenants + clean up their roles
            $toDetach = array_diff($record->tenants->pluck('id')->toArray(), $assignments);
            foreach ($toDetach as $tenantId) {
                $tenant = Tenant::find($tenantId);
                if ($tenant) {
                    $roleService->removeAllRolesInTenant($record, $tenant);
                }
            }

            $record->tenants()->sync($assignments);

            // Assign Redakteur role in each tenant
            foreach ($assignments as $tenantId) {
                $tenant = Tenant::find($tenantId);
                if ($tenant) {
                    $roleService->createDefaultRolesForTenant($tenant);
                    $roleService->assignRoleInTenant($record, 'Redakteur', $tenant);
                }
            }

            $record->forceFill(['is_admin' => false])->save();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
