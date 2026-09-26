<?php

namespace Codewiser\Otp\Tests;

use Carbon\Carbon;
use Codewiser\Fortify\AssetsServiceProvider;
use Codewiser\Otp\Notifications\OtpNotification;
use Codewiser\Otp\Otp;
use Codewiser\Otp\OtpServiceProvider;
use Illuminate\Contracts\Session\Session;
use Laravel\Fortify\FortifyServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use ReflectionProperty;

abstract class TestCase extends BaseTestCase
{
    protected function tearDown(): void
    {
        // Callbacks are not nullable, so they are unregistered by reflection.
        $this->forgetCallback(Otp::class, 'newCodeCallback');

        $this->forgetCallback(OtpNotification::class, 'toMailCallback');

        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * Unregister a static callback.
     */
    private function forgetCallback(string $class, string $property): void
    {
        (new ReflectionProperty($class, $property))->setValue(null, null);
    }

    /**
     * Session flags left by the users that passed the otp.
     *
     * @return array<string, mixed>
     */
    protected function otpPassed(?Session $session = null): array
    {
        $session = $session ?: session();

        return array_filter(
            $session->all(),
            fn($value, $key) => str_starts_with((string) $key, 'otp_passed'),
            ARRAY_FILTER_USE_BOTH
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            FortifyServiceProvider::class,
            AssetsServiceProvider::class,
            OtpServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('app.key', 'base64:HDYqVlXI3V0hnqDcV/wAI/cmDX9cHschsamxPQ24kfk=');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');

        // Mimic the published app service provider: the package does not bind
        // Otp on its own. Resolved lazily, so that a test case may still swap
        // the user provider driver.
        $app->singleton(Otp::class, fn($app) => new Otp(
            $app['auth']->createUserProvider('users'),
            $app['auth']->guard('web')
        ));

        // Mimic the published app service provider: register default views.
        Otp::loginView('otp::login');
        Otp::verifyEmailView('otp::verify-email');
    }
}