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
        RateLimiter::for('auth-register', function (Request $request) {
            return [
                Limit::perMinute(5)->by($request->ip()),
            ];
        });

        RateLimiter::for('auth-login', function (Request $request) {
            return [
                Limit::perMinute(5)->by($request->ip()),
                Limit::perMinute(10)->by((string) $request->input('email')),
            ];
        });

        RateLimiter::for('auth-forgot-password', function (Request $request) {
            return [
                Limit::perMinute(3)->by($request->ip()),
                Limit::perMinute(5)->by((string) $request->input('email')),
            ];
        });

        RateLimiter::for('auth-reset-password', function (Request $request) {
            return [
                Limit::perMinute(5)->by($request->ip()),
                Limit::perMinute(10)->by((string) $request->input('email')),
            ];
        });
    }
}
