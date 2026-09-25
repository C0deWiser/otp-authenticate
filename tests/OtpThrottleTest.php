<?php

namespace Codewiser\Otp\Tests;

use Codewiser\Otp\Otp;
use Codewiser\Otp\RateLimiter\OtpRateLimiter;
use Codewiser\Otp\Tests\Fakes\User;
use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;

class OtpThrottleTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app->instance(Otp::class, new Otp);

        $app->make(RateLimiter::class)->for(OtpRateLimiter::ISSUE, fn (Request $request) => [
            Limit::perMinute(1)->by('user:'.$request->user()?->getAuthIdentifier()),
        ]);

        $app->make(RateLimiter::class)->for(OtpRateLimiter::VERIFY, fn (Request $request) => [
            Limit::perMinute(1)->by('user:'.$request->user()?->getAuthIdentifier())
                ->response(fn () => response('custom throttled', 429)),
        ]);
    }

    public function test_throttled_user_gets_redirect_with_error_instead_of_429()
    {
        $user = new User;

        $this->actingAs($user);

        $this->post('/otp/email', ['send' => ''])
            ->assertRedirect();

        $this->assertCount(1, $user->sentOtps);

        $response = $this->post('/otp/email', ['send' => '']);

        $response->assertStatus(302);
        $response->assertSessionHasErrors([
            'code' => trans('otp::messages.'.Otp::THROTTLE, ['seconds' => 60]),
        ]);
        $this->assertCount(1, $user->sentOtps);
    }
}