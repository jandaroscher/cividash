<?php

namespace App\Providers;

use App\Models\Page;
use App\Observers\PageObserver;
use Illuminate\Support\ServiceProvider;
use BezhanSalleh\FilamentLanguageSwitch\LanguageSwitch;
use Z3d0X\FilamentFabricator\Facades\FilamentFabricator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        LanguageSwitch::configureUsing(function (LanguageSwitch $switch) {
            $switch
                ->locales(['en', 'de'])
                ->visible();
        });

        // Register Page observer for cache invalidation
        Page::observe(PageObserver::class);

        // Register Fabricator layouts for use outside admin panel
        // This ensures layouts are available when PageController is called from web routes
        // Blocks are auto-registered, but layouts need manual registration
        $this->registerFabricatorLayouts();
    }

    /**
     * Register Fabricator layouts from config.
     */
    protected function registerFabricatorLayouts(): void
    {
        $config = config('filament-fabricator.layouts', []);
        $layoutsToRegister = $config['register'] ?? [];

        foreach ($layoutsToRegister as $layoutClass) {
            if (class_exists($layoutClass)) {
                FilamentFabricator::registerLayout($layoutClass);
            }
        }
    }
}
