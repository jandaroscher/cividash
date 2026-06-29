<?php

namespace App\Providers;

use App\Contracts\Integration\DataMapperInterface;
use App\Contracts\Integration\ExternalDataSourceInterface;
use App\Contracts\Integration\SyncServiceInterface;
use App\Contracts\Integration\WritableDataSourceInterface;
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

        // Write-back: only NGSI-LD/Stellio supports publishing back to
        // CORE. SensorThings stays read-only, so resolving the writable binding
        // under any other driver is a programming error — fail loudly instead of
        // handing out a silently broken client.
        $this->app->bind(WritableDataSourceInterface::class, function () {
            if (config('integrations.civitas.driver', 'ngsi-ld') !== 'ngsi-ld') {
                throw new \LogicException('Write-back to CORE requires the ngsi-ld driver; the current driver is read-only.');
            }

            return NgsiLdClient::fromConfig();
        });
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

        // Serialise an aggregate (max) read against concurrent writers. MySQL/
        // MariaDB/SQLite use "SELECT max(...) ... FOR UPDATE". PostgreSQL forbids
        // FOR UPDATE with aggregate functions, so instead we take a transaction-
        // scoped advisory lock keyed on $lockName; the caller MUST run this inside
        // the same transaction that performs the subsequent write, so the lock
        // spans the read+write window (it is released on commit/rollback).
        EloquentBuilder::macro('lockForUpdateForAggregate', function (string $lockName) {
            /** @var EloquentBuilder $this */
            $connection = $this->getConnection();
            $driver = $connection->getDriverName();

            if (in_array($driver, ['pgsql', 'postgres', 'postgresql'], true)) {
                $key = (int) hexdec(substr(hash('sha256', $lockName), 0, 15));
                $connection->statement('SELECT pg_advisory_xact_lock(?)', [$key]);

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
            // Whitelist the locale before interpolating it into raw SQL, mirroring
            // App\Traits\BuildsJsonLocaleExpressions.
            if (! in_array($locale, ['de', 'en'], true)) {
                throw new \InvalidArgumentException("Unsupported locale [{$locale}] for whereTranslation().");
            }

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
