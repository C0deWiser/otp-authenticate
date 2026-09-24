<?php

namespace Codewiser\Otp;

use Codewiser\Otp\Console\InstallCommand;
use Codewiser\Otp\RateLimiter\OtpRateLimiter;
use Codewiser\Otp\RateLimiter\Throttle;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Cache\RateLimiting\Unlimited;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;

class OtpServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'otp');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        $this->publishes([
            __DIR__.'/../resources/views'              => resource_path('views/vendor/otp'),
            __DIR__.'/../stubs/OtpServiceProvider.php' => app_path('Providers/OtpServiceProvider.php'),
        ], 'otp');

        $this->app->booted(function () {
            $rateLimiter = $this->app->make(RateLimiter::class);

            foreach (Throttle::cases() as $throttle) {
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
                        static fn (Limit $limit) => $limit->responseCallback
                            ? $limit
                            : $limit->response(
                                fn (Request $request, array $headers) => redirect()
                                    ->back(302, $headers)
                                    ->with('status', OtpService::OTP_THROTTLE)
                                    ->with('delay', OtpRateLimiter::for($throttle, $request)->forHumans())
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