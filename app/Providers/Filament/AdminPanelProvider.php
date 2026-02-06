<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
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
use Illuminate\Support\Facades\Storage;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Z3d0X\FilamentFabricator\Enums\BlockPickerStyle;
use Z3d0X\FilamentFabricator\FilamentFabricatorPlugin;

class AdminPanelProvider extends PanelProvider
{
    /**
     * Registers frontend assets used by the Filament admin panel.
     *
     * Registers the "dotlottie-player" JavaScript (built via Vite) and the local "admin-overrides" CSS file with Filament's asset registry so they are available in the admin UI.
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
     *
     * Binds the Tenant model, registers tenant-specific UI pages and navigation (including an optional favicon resolved from GeneralSettings), discovers resources/pages/widgets, sets panel identifiers and UI options, and returns the configured Panel.
     *
     * @param  Panel  $panel  The Panel instance to configure.
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

        // Resolve favicon from settings
        $faviconUrl = null;
        try {
            $settings = app(GeneralSettings::class);
            if ($settings->favicon) {
                $faviconUrl = Storage::disk('public')->url($settings->favicon);
            }
        } catch (\Throwable $e) {
            // Settings might not be migrated yet
        }

        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName('Nachhaltigkeits-Dashboard')
            ->favicon($faviconUrl)
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
                    ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.tenant.profile'))
                    ->visible(fn (): bool => Filament::getTenant() !== null),
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
            ])
            ->middleware([
                \App\Http\Middleware\LocaleDetector::class,
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
