<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        RateLimiter::for('jwt-login', function (Request $request) {
            $maxAttempts = (int) env('JWT_LOGIN_RATE_LIMIT', 5);
            $decayMinutes = (int) env('JWT_LOGIN_RATE_LIMIT_MINUTES', 1);

            return Limit::perMinutes($decayMinutes, $maxAttempts)->by($request->ip());
        });

        RateLimiter::for('sso-init', function (Request $request) {
            $maxAttempts = (int) env('SSO_INIT_RATE_LIMIT', 30);
            $decayMinutes = (int) env('SSO_INIT_RATE_LIMIT_MINUTES', 1);

            return Limit::perMinutes($decayMinutes, $maxAttempts)->by($request->ip());
        });

        RateLimiter::for('sso-exchange', function (Request $request) {
            $maxAttempts = (int) env('SSO_EXCHANGE_RATE_LIMIT', 60);
            $decayMinutes = (int) env('SSO_EXCHANGE_RATE_LIMIT_MINUTES', 1);

            return Limit::perMinutes($decayMinutes, $maxAttempts)->by($request->ip());
        });
    }
}
