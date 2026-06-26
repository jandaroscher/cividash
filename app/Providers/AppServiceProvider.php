<?php

namespace App\Providers;

use App\Contracts\Integration\DataMapperInterface;
use App\Contracts\Integration\ExternalDataSourceInterface;
use App\Contracts\Integration\SyncServiceInterface;
use App\Models\Page;
use App\Models\PersonalAccessToken;
use App\Observers\PageObserver;
use App\Services\Integration\CivitasDataMapper;
use App\Services\Integration\NgsiLdClient;
use App\Services\Integration\NgsiLdDataMapper;
use App\Services\Integration\SensorThingsClient;
use App\Services\Integration\SyncService;
use BezhanSalleh\FilamentLanguageSwitch\Events\LocaleChanged;
use BezhanSalleh\FilamentLanguageSwitch\LanguageSwitch;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\Sanctum;
use SocialiteProviders\Keycloak\KeycloakExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;
use Z3d0X\FilamentFabricator\Forms\Components\PageBuilder;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ExternalDataSourceInterface::class, fn () => config('integrations.civitas.driver') === 'sensorthings'
            ? SensorThingsClient::fromConfig()
            : NgsiLdClient::fromConfig());
        $this->app->bind(DataMapperInterface::class, fn () => config('integrations.civitas.driver') === 'sensorthings'
            ? new CivitasDataMapper
            : new NgsiLdDataMapper);
        $this->app->bind(SyncServiceInterface::class, SyncService::class);
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

        // Apply a FOR UPDATE row lock only on drivers that permit it together with
        // aggregate functions. PostgreSQL rejects "SELECT max(...) ... FOR UPDATE"
        // ("FOR UPDATE is not allowed with aggregate functions"), so the lock is
        // skipped there; the surrounding DB::transaction() still provides the
        // consistency needed for sequential position/id assignment.
        EloquentBuilder::macro('lockForUpdateForAggregate', function () {
            /** @var EloquentBuilder $this */
            $driver = $this->getConnection()->getDriverName();

            if (in_array($driver, ['pgsql', 'postgres', 'postgresql'], true)) {
                return $this;
            }

            return $this->lockForUpdate();
        });

        // Match a translatable JSON column on an exact locale value, portably.
        // Laravel's whereJsonContains()/where('col->de', ...) emit the json `->`
        // operator, which PostgreSQL rejects on columns physically stored as varchar
        // (several translatable columns are). This builds the right per-driver
        // expression (see App\Traits\BuildsJsonLocaleExpressions for the dialects).
        EloquentBuilder::macro('whereTranslation', function (string $column, string $locale, string $value) {
            /** @var EloquentBuilder $this */
            $driver = $this->getConnection()->getDriverName();

            if (in_array($driver, ['pgsql', 'postgres', 'postgresql'], true)) {
                $expr = sprintf("CAST(%s AS json)->>'%s'", $column, $locale);
            } elseif ($driver === 'sqlite') {
                $expr = sprintf("json_extract(%s, '$.\"%s\"')", $column, $locale);
            } else {
                $expr = sprintf("JSON_UNQUOTE(JSON_EXTRACT(%s, '$.\"%s\"'))", $column, $locale);
            }

            return $this->whereRaw("{$expr} = ?", [$value]);
        });

        LanguageSwitch::configureUsing(function (LanguageSwitch $switch) {
            $switch
                ->locales(['en', 'de'])
                ->visible();
        });

        // Configure PageBuilder to make blocks collapsible
        PageBuilder::configureUsing(function (PageBuilder $builder) {
            $builder->collapsible()->collapsed();
        });

        // Persist language switcher changes to user's DB locale
        Event::listen(LocaleChanged::class, function (LocaleChanged $event) {
            if ($user = Auth::user()) {
                $user->update(['locale' => $event->locale]);
            }
        });

        // Configure default password validation rules (min 12, max 64 characters)
        Password::defaults(function () {
            return Password::min(12)->max(64);
        });

        // Register Page observer for cache invalidation
        Page::observe(PageObserver::class);

        // Register Keycloak Socialite provider for SSO
        Event::listen(SocialiteWasCalled::class, [KeycloakExtendSocialite::class, 'handle']);

        // Named rate limiter for export endpoints.
        // Separate bucket from the general API throttle so normal
        // page/tile/config fetches don't eat into the export quota.
        RateLimiter::for('export', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });
    }
}
