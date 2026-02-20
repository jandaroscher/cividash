<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\EditProfile;
use App\Filament\Pages\Tenancy\EditTenantProfile;
use App\Filament\Pages\Tenancy\RegisterTenant;
use App\Http\Middleware\SetFilamentDefaultTenant;
use App\Models\Tenant;
use App\Settings\GeneralSettings;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\SpatieLaravelTranslatablePlugin;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentAsset;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Z3d0X\FilamentFabricator\Enums\BlockPickerStyle;
use Z3d0X\FilamentFabricator\FilamentFabricatorPlugin;

class AdminPanelProvider extends PanelProvider
{
    /**
     * Registers frontend assets used by the Filament admin panel.
     */
    public function boot(): void
    {
        FilamentAsset::register([
            Js::make('dotlottie-player', asset('js/vendor/dotlottie-player.js')),
            Css::make('admin-overrides', asset('css/filament/admin-overrides.css')),
        ]);
    }

    /**
     * Configure the Filament admin panel with tenancy, tenant pages, navigation, resources, widgets, middleware, plugins, and authentication middleware.
     */
    public function panel(Panel $panel): Panel
    {
        // Enable tenancy on the panel to allow Filament::getTenant() to work in tests
        $panel->tenant(Tenant::class);

        // Register tenant UI pages so route-based navigation works in all environments
        $panel
            ->tenantRegistration(RegisterTenant::class)
            ->tenantProfile(EditTenantProfile::class);

        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName(fn (): string => __('filament.brand_name'))
            ->favicon(function () {
                try {
                    $settings = app(GeneralSettings::class);
                    if ($settings->favicon) {
                        return Storage::disk('public')->url($settings->favicon);
                    }
                } catch (\Throwable $e) {
                    // Settings might not be migrated yet
                }

                return null;
            })
            ->sidebarCollapsibleOnDesktop()
            ->login()
            ->globalSearch(false)
            ->userMenuItems([
                'profile' => MenuItem::make()
                    ->label(fn (): string => __('filament.pages.edit_profile.title'))
                    ->icon('heroicon-o-user-circle')
                    ->url(fn (): string => Filament::getTenant()
                        ? EditProfile::getUrl(tenant: Filament::getTenant())
                        : '#'
                    ),
            ])
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->navigationItems([
                NavigationItem::make('dashboard-config')
                    ->label(fn (): string => __('filament.pages.edit_dashboard_config.title'))
                    ->group(fn (): string => __('filament.navigation.groups.settings'))
                    ->icon('heroicon-o-building-office-2')
                    ->sort(24)
                    ->url(fn (): string => Filament::getTenant()
                        ? route('filament.admin.tenant.profile', ['tenant' => Filament::getTenant()])
                        : '#'
                    )
                    ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.tenant.profile'))
                    ->visible(fn (): bool => (bool) Auth::user()?->is_admin),
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
                // LocaleDetector must run after session is started so Auth::user() works
                \App\Http\Middleware\LocaleDetector::class,
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
