<?php

namespace App\Providers;

use Codewiser\Otp\OtpService;
use Codewiser\Otp\RateLimiter\Throttle;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class OtpServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(OtpService::class,
            fn($app) => new OtpService()
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Named RateLimiter for issuing otp code
        RateLimiter::for(Throttle::issue, fn(Request $request) => [
            // Limit::perMinute(1)->by('minute:'.$request->user()->id),
            // Limit::perDay(15)->by('day:'.$request->user()->id),
        ]);

        // Named RateLimiter for verifying otp code (bruteforce protection)
        RateLimiter::for(Throttle::verify, fn(Request $request) => [
            // Limit::perDay(30)->by($request->user()->id)
        ]);
    }
}
