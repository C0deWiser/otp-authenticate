<?php

namespace Codewiser\Otp\Tests;

use Codewiser\Otp\OtpServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app)
    {
        return [OtpServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('app.key', 'base64:HDYqVlXI3V0hnqDcV/wAI/cmDX9cHschsamxPQ24kfk=');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');
    }
}