<?php

namespace Codewiser\Otp;

use Codewiser\Fortify\NavStack;
use Codewiser\Otp\Console\InstallCommand;
use Codewiser\Otp\Contracts\CodeSentResponse;
use Codewiser\Otp\Contracts\CodeVerifiedResponse;
use Codewiser\Otp\Contracts\LoginViewResponse;
use Codewiser\Otp\Contracts\ThrottledResponse;
use Codewiser\Otp\Contracts\VerifyEmailViewResponse;
use Codewiser\Otp\Http\Middleware\EnsureEmailIsVerifiedWithOtp;
use Codewiser\Otp\Http\Responses\CodeSent;
use Codewiser\Otp\Http\Responses\CodeVerified;
use Codewiser\Otp\Http\Responses\Throttled;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

class OtpServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind('verified.otp', EnsureEmailIsVerifiedWithOtp::class);

        $this->app->singleton(CodeSentResponse::class, CodeSent::class);
        $this->app->singleton(ThrottledResponse::class, Throttled::class);
        $this->app->singleton(CodeVerifiedResponse::class, CodeVerified::class);

        $this->app->extend(CodeSentResponse::class, fn($object) => $this->withLogger($object, logger()));
        $this->app->extend(LoginViewResponse::class, fn($object) => $this->withLogger($object, logger()));
        $this->app->extend(ThrottledResponse::class, fn($object) => $this->withLogger($object, logger()));
        $this->app->extend(CodeVerifiedResponse::class, fn($object) => $this->withLogger($object, logger()));
        $this->app->extend(VerifyEmailViewResponse::class, fn($object) => $this->withLogger($object, logger()));

        $this->app->extend(NavStack::class, function (NavStack $stack) {
            return $stack->when(auth()->user(),
                fn(NavStack $stack, Authenticatable $user) => $stack
                    ->push('otp-passed.example', '<code>verified.otp</code>', 5),
                fn(NavStack $stack) => $stack
                    ->push('otp.login', __('OTP Sign In'), 1)
            );
        });
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

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class
            ]);
        }
    }

    protected function withLogger(object $object, LoggerInterface $logger): object
    {
        if (method_exists($object, 'setLogger')) {
            $object->setLogger($logger);
        }

        return $object;
    }
}