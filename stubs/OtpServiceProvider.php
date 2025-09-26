<?php

namespace App\Providers;

use Codewiser\Otp\OtpService;
use Codewiser\Otp\RateLimiter\OtpLimit;
use Codewiser\Otp\RateLimiter\Throttle;
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
        RateLimiter::for(Throttle::issue, fn(Request $request) => [
            OtpLimit::perMinute(1)->for(Throttle::issue)->by('minute:'.$request->user()->id),
            OtpLimit::perDay(15)->for(Throttle::issue)->by('day:'.$request->user()->id),
        ]);

        RateLimiter::for(Throttle::verify, fn(Request $request) => [
            OtpLimit::perDay(30)->for(Throttle::verify)->by($request->user()->id)
        ]);
    }
}
