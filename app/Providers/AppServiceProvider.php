<?php

namespace App\Providers;

use App\Models\Page;
use App\Models\PersonalAccessToken;
use App\Observers\PageObserver;
use BezhanSalleh\FilamentLanguageSwitch\Events\LocaleChanged;
use BezhanSalleh\FilamentLanguageSwitch\LanguageSwitch;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use Z3d0X\FilamentFabricator\Forms\Components\PageBuilder;

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
     * Bootstrap application services and configure runtime integrations.
     *
     * Configures language switch locales and visibility, enables collapsible blocks for the PageBuilder,
     * and registers the Page model observer for cache invalidation.
     *
     * Note: Filament Fabricator layouts are auto-discovered by the package when Layout classes extend
     * Z3d0X\FilamentFabricator\Layouts\Layout with a protected static $name property in the configured layouts directory.
     */
    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        LanguageSwitch::configureUsing(function (LanguageSwitch $switch) {
            $switch
                ->locales(['en', 'de'])
                ->visible();
        });

        // Configure PageBuilder to make blocks collapsible
        PageBuilder::configureUsing(function (PageBuilder $builder) {
            $builder->collapsible();
        });

        // Persist language switcher changes to user's DB locale
        Event::listen(LocaleChanged::class, function (LocaleChanged $event) {
            if ($user = Auth::user()) {
                $user->update(['locale' => $event->locale]);
            }
        });

        // Register Page observer for cache invalidation
        Page::observe(PageObserver::class);
    }
}
