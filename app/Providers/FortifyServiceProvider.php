<?php

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Laravel\Fortify\Contracts\PasswordResetResponse as PasswordResetResponseContract;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register JSON response classes for headless SPA
        $this->app->singleton(LoginResponseContract::class, JsonLoginResponse::class);
        $this->app->singleton(LogoutResponseContract::class, JsonLogoutResponse::class);
        $this->app->singleton(PasswordResetResponseContract::class, JsonPasswordResetResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        // Rate limiter for login endpoint
        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}

/**
 * JSON response for successful login.
 */
class JsonLoginResponse implements LoginResponseContract
{
    public function toResponse($request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'message' => __('auth.login_success'),
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'locale' => $user->locale,
                'email_verified_at' => $user->email_verified_at,
            ],
        ]);
    }
}

/**
 * JSON response for successful logout.
 */
class JsonLogoutResponse implements LogoutResponseContract
{
    public function toResponse($request): JsonResponse
    {
        return response()->json([
            'message' => __('auth.logout_success'),
        ]);
    }
}

/**
 * JSON response for successful password reset.
 */
class JsonPasswordResetResponse implements PasswordResetResponseContract
{
    public function toResponse($request): JsonResponse
    {
        return response()->json([
            'message' => __('passwords.reset'),
        ]);
    }
}
