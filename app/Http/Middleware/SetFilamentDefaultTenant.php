<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Filament\Models\Contracts\HasDefaultTenant as HasDefaultTenantContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class SetFilamentDefaultTenant
{
    public function handle(Request $request, Closure $next)
    {
        if (Filament::hasTenancy() && Filament::getTenant() === null && Schema::hasTable('tenants')) {
            $user = Filament::auth()->user();

            if ($user instanceof HasDefaultTenantContract) {
                $panel = Filament::getCurrentPanel();
                $tenant = $user->getDefaultTenant($panel);

                if ($tenant) {
                    Filament::setTenant($tenant);
                    if ($request->hasSession()) {
                        $request->session()->put('filament.tenant', $tenant->getKey());
                    }
                }
            } elseif (app()->runningUnitTests()) {
                $tenant = \App\Models\Tenant::firstOrCreate(
                    ['slug' => 'default'],
                    ['name' => 'Default Tenant']
                );

                Filament::setTenant($tenant);
                if ($request->hasSession()) {
                    $request->session()->put('filament.tenant', $tenant->getKey());
                }
            }
        }

        return $next($request);
    }
}
