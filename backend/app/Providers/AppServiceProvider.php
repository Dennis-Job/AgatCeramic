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
        RateLimiter::for('api', static fn (Request $request): Limit => Limit::perMinute(60)->by($request->ip()));

        RateLimiter::for('login', static function (Request $request): Limit {
            $identity = strtolower($request->input('email', '')).'|'.$request->ip();

            return Limit::perMinute(5)->by(hash('sha256', $identity));
        });
    }
}
