<?php

namespace Codewiser\Otp\Console;

use Codewiser\Otp\OtpServiceProvider;
use Illuminate\Console\Command;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'otp:install')]
class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'otp:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install all of the OTP resources';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->callSilent('vendor:publish', [
            '--provider' => OtpServiceProvider::class,
        ]);

        $this->registerOtpServiceProvider();

        $this->components->info('OTP scaffolding installed successfully.');
    }

    /**
     * Register the Fortify service provider in the application configuration file.
     */
    protected function registerOtpServiceProvider(): void
    {
        if (! method_exists(ServiceProvider::class, 'addProviderToBootstrapFile')) {
            return;
        }

        ServiceProvider::addProviderToBootstrapFile(\App\Providers\OtpServiceProvider::class);
    }
}
