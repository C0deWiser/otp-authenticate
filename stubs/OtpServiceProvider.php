<?php

namespace App\Providers;

use Codewiser\Otp\OtpLimiter;
use Codewiser\Otp\OtpService;
use Codewiser\Otp\Throttle;
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
        RateLimiter::for(Throttle::issue, function (Request $request) {

            $response = OtpLimiter::for(Throttle::issue, $request)->response();

            return [
                Limit::perMinute(1)->response($response)->by('minute:'.$request->user()->id),
                Limit::perDay(15)->response($response)->by('day:'.$request->user()->id),
            ];
        });

        RateLimiter::for(Throttle::verify, function (Request $request) {

            $response = OtpLimiter::for(Throttle::verify, $request)->response();

            return Limit::perDay(30)->response($response)->by($request->user()->id);
        });
    }
}
