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
        if ($path = config('cms.public_path')) {
            $this->app->usePublicPath($path);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('application-token', fn (Request $request) => Limit::perMinute(30)->by(hash('sha256', $request->ip())));
        RateLimiter::for('application-submit', fn (Request $request) => [
            Limit::perMinute(5)->by('minute:'.hash('sha256', $request->ip())),
            Limit::perHour(20)->by('hour:'.hash('sha256', $request->ip())),
        ]);
    }
}
