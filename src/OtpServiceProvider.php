<?php

namespace Codewiser\Otp;

use Codewiser\Otp\Console\InstallCommand;
use Codewiser\Otp\Contracts\CodeVerifiedResponse;
use Codewiser\Otp\Contracts\SendRequestResponse;
use Codewiser\Otp\Http\Responses\CodeSent;
use Codewiser\Otp\Http\Responses\CodeVerified;
use Codewiser\Otp\RateLimiter\OtpRateLimiter;
use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Cache\RateLimiting\Unlimited;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;

class OtpServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(
            SendRequestResponse::class,
            CodeSent::class
        );

        $this->app->singleton(
            CodeVerifiedResponse::class,
            CodeVerified::class
        );
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'otp');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'otp');

        $this->publishes([
            __DIR__.'/../lang'                         => lang_path('vendor/otp'),
            __DIR__.'/../public'                       => public_path('vendor/otp'),
            __DIR__.'/../resources/views'              => resource_path('views/vendor/otp'),
            __DIR__.'/../stubs/OtpServiceProvider.php' => app_path('Providers/OtpServiceProvider.php'),
        ], 'otp');

        $this->app->booted(function () {
            $rateLimiter = $this->app->make(RateLimiter::class);

            foreach ([OtpRateLimiter::ISSUE, OtpRateLimiter::VERIFY] as $throttle) {
                $original = $rateLimiter->limiter($throttle);

                if (! $original) {
                    continue;
                }

                $rateLimiter->for($throttle, static function (Request $request) use ($original, $throttle) {
                    $limits = $original($request);

                    if ($limits instanceof Unlimited) {
                        return $limits;
                    }

                    return array_map(
                        static fn(Limit $limit) => $limit->responseCallback
                            ? $limit
                            : $limit->response(
                                OtpRateLimiter::for($throttle, $request)->response()
                            ),
                        (array) $limits
                    );
                });
            }
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class
            ]);
        }
    }
}