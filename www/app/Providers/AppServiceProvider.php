<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configurePassport();
        $this->configureRateLimiting();
    }

    /**
     * Give tokens an explicit, configurable lifetime instead of relying on
     * Passport's non-expiring defaults.
     */
    private function configurePassport(): void
    {
        Passport::personalAccessTokensExpireIn(
            now()->addDays((int) config('passport.personal_access_token_ttl_days')),
        );
        Passport::tokensExpireIn(
            now()->addDays((int) config('passport.access_token_ttl_days')),
        );
        Passport::refreshTokensExpireIn(
            now()->addDays((int) config('passport.refresh_token_ttl_days')),
        );
    }

    /**
     * Named limiters for the public auth endpoints. Login is keyed by both
     * email and IP so one attacker cannot lock out an account from many IPs,
     * nor spray many accounts from one IP.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $email = Str::lower((string) $request->input('email'));

            return [
                Limit::perMinute(5)->by($email.'|'.$request->ip()),
                Limit::perMinute(30)->by((string) $request->ip()),
            ];
        });

        RateLimiter::for('register', fn (Request $request) => Limit::perMinute(10)->by((string) $request->ip()));
    }
}
