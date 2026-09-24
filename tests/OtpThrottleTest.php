<?php

namespace Codewiser\Otp\Tests;

use Codewiser\Otp\Otp;
use Codewiser\Otp\OtpVerify;
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

        $app->instance(OtpVerify::class, new OtpVerify('P1W'));

        $app->make(RateLimiter::class)->for(OtpRateLimiter::ISSUE, fn (Request $request) => [
            Limit::perMinute(1)->by('user:'.$request->user()?->getAuthIdentifier()),
        ]);

        $app->make(RateLimiter::class)->for(OtpRateLimiter::VERIFY, fn (Request $request) => [
            Limit::perMinute(1)->by('user:'.$request->user()?->getAuthIdentifier())
                ->response(fn () => response('custom throttled', 429)),
        ]);
    }

    public function test_plain_limit_is_decorated_at_runtime()
    {
        $limits = $this->app->make(RateLimiter::class)
            ->limiter(OtpRateLimiter::ISSUE)(Request::create('/email/otp', 'POST'));

        $this->assertCount(1, $limits);
        $this->assertIsCallable($limits[0]->responseCallback);
    }

    public function test_throttled_user_gets_redirect_with_delay_instead_of_429()
    {
        $user = new User;

        $this->actingAs($user);

        $this->post('/email/otp')
            ->assertRedirect();

        $this->assertCount(1, $user->sentOtps);

        $response = $this->post('/email/otp');

        $response->assertStatus(302);
        $response->assertSessionHas('status', Otp::OTP_THROTTLE);
        $response->assertSessionHas('delay');
        $this->assertCount(1, $user->sentOtps);
    }

    public function test_throttle_response_carries_rate_limit_headers()
    {
        $this->actingAs(new User);

        $this->post('/email/otp');
        $this->post('/email/otp')
            ->assertHeader('X-RateLimit-Limit', '1')
            ->assertHeader('X-RateLimit-Remaining', '0');
    }

    public function test_limit_with_own_response_is_left_untouched()
    {
        $this->actingAs(new User);

        $this->put('/email/otp', ['otp' => '000000'])
            ->assertSessionHasErrors('otp');

        $this->put('/email/otp', ['otp' => '000000'])
            ->assertStatus(429)
            ->assertSee('custom throttled');
    }
}