<?php

namespace App\Providers;

use Codewiser\Otp\Otp;
use Codewiser\Otp\OtpAuthenticate;
use Codewiser\Otp\RateLimiter\OtpRateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;

class OtpServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(OtpAuthenticate::class,
            fn($app) => new OtpAuthenticate(
                Auth::createUserProvider('users'),
                Auth::guard('web')
            ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Otp::loginView('otp::login');
        Otp::verifyEmailView('otp::verify-email');

        RateLimiter::for(OtpRateLimiter::ISSUE, function (Request $request) {

            $throttleKey =
                $request->user()?->id.'|'.
                $request->input(Fortify::email()).'|'.
                $request->ip();

            return [
                Limit::perMinute(1)->by('minute:'.$throttleKey),
                Limit::perDay(15)->by('day:'.$throttleKey),
            ];
        });

        RateLimiter::for(OtpRateLimiter::VERIFY, function (Request $request) {

            $throttleKey =
                $request->user()?->id.'|'.
                $request->input(Fortify::email()).'|'.
                $request->ip();

            return [
                Limit::perDay(30)->by($throttleKey)
            ];
        });
    }
}