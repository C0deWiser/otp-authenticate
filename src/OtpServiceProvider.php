<?php

namespace Codewiser\Otp;

use Codewiser\Otp\Console\InstallCommand;
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

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class
            ]);
        }
    }
}