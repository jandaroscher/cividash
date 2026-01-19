<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Tenancy\EditTenantProfile;
use App\Filament\Pages\Tenancy\RegisterTenant;
use App\Models\Tenant;
use App\Http\Middleware\SetFilamentDefaultTenant;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Filament\SpatieLaravelTranslatablePlugin;
use Z3d0X\FilamentFabricator\FilamentFabricatorPlugin;
use Z3d0X\FilamentFabricator\Enums\BlockPickerStyle;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    /**
     * Configure the Filament admin Panel with tenancy, UI pages, resources, widgets, middleware, plugins, and authentication.
     *
     * Also binds the Tenant model and registers tenant-specific pages so tenant-aware navigation and route resolution work across environments (including tests).
     *
     * @param Panel $panel The Panel instance to configure.
     * @return Panel The configured Panel instance.
     */
    public function panel(Panel $panel): Panel
    {
        // Enable tenancy on the panel to allow Filament::getTenant() to work in tests
        // This ensures that setTenant()/getTenant() work reliably even in test contexts
        $panel->tenant(Tenant::class);

        // Register tenant UI pages so route-based navigation works in all environments,
        // including tests that render the panel navigation.
        $panel
            ->tenantRegistration(RegisterTenant::class)
            ->tenantProfile(EditTenantProfile::class);

        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName('Nachhaltigkeits-Dashboard')
            ->sidebarCollapsibleOnDesktop()
            ->login()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->navigationItems([
                NavigationItem::make('tenant-profile')
                    ->label(fn (): string => __('filament.pages.edit_tenant_profile.title'))
                    ->group('Einstellungen')
                    ->icon('heroicon-o-building-office-2')
                    ->sort(24)
                    ->url(fn (): string => Filament::getTenant()
                        ? route('filament.admin.tenant.profile', ['tenant' => Filament::getTenant()])
                        : '#'
                    )
                    ->visible(fn (): bool => Filament::getTenant() !== null),
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->plugins([
                SpatieLaravelTranslatablePlugin::make()
                    ->defaultLocales(['de', 'en']),
                FilamentFabricatorPlugin::make()
                    ->blockPickerStyle(BlockPickerStyle::Modal),
            ])
            ->authMiddleware([
                Authenticate::class,
                SetFilamentDefaultTenant::class,
            ]);
    }
}