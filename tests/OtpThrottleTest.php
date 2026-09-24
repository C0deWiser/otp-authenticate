<?php

namespace Codewiser\Otp\Tests;

use Codewiser\Otp\OtpService;
use Codewiser\Otp\RateLimiter\Throttle;
use Codewiser\Otp\Tests\Fakes\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;

class OtpThrottleTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app->make(RateLimiter::class)->for(Throttle::issue, fn (Request $request) => [
            Limit::perMinute(1)->by('user:'.$request->user()?->getAuthIdentifier()),
        ]);

        $app->make(RateLimiter::class)->for(Throttle::verify, fn (Request $request) => [
            Limit::perMinute(1)->by('user:'.$request->user()?->getAuthIdentifier())
                ->response(fn () => response('custom throttled', 429)),
        ]);
    }

    public function test_plain_limit_is_decorated_at_runtime()
    {
        $limits = $this->app->make(RateLimiter::class)
            ->limiter(Throttle::issue)(Request::create('/email/otp', 'POST'));

        $this->assertCount(1, $limits);
        $this->assertIsCallable($limits[0]->responseCallback);
    }

    public function test_throttled_user_gets_redirect_with_delay_instead_of_429()
    {
        $this->actingAs(new User);

        $this->post('/email/otp')
            ->assertSessionHas('otp');

        $response = $this->post('/email/otp');

        $response->assertStatus(302);
        $response->assertSessionHas('status', OtpService::OTP_THROTTLE);
        $response->assertSessionHas('delay');
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