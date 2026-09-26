<?php

namespace Codewiser\Otp\Tests;

use Carbon\Carbon;
use Codewiser\Otp\Otp;
use Codewiser\Otp\RateLimiter\OtpRateLimiter;
use Codewiser\Otp\Tests\Fakes\Guard;
use Codewiser\Otp\Tests\Fakes\User;
use Codewiser\Otp\Tests\Fakes\UserProvider;
use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter as RateLimiterFacade;

class OtpThrottleTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app->instance(Otp::class, new Otp(new UserProvider, new Guard));

        $app->make(RateLimiter::class)->for(OtpRateLimiter::ISSUE, fn (Request $request) => [
            Limit::perMinute(1)->by('user:'.$request->user()?->getAuthIdentifier()),
        ]);

        $app->make(RateLimiter::class)->for(OtpRateLimiter::VERIFY, fn (Request $request) => [
            Limit::perMinute(1)->by('user:'.$request->user()?->getAuthIdentifier())
                ->response(fn () => response('custom throttled', 429)),
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Freeze the clock, so that the retry-after of a freshly hit limit is
        // not affected by a second boundary crossed mid-test.
        Carbon::setTestNow(Carbon::now());
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

    public function test_throttled_json_request_gets_429()
    {
        RateLimiterFacade::for(OtpRateLimiter::VERIFY, fn (Request $request) => [
            Limit::perMinute(1)->by('user:'.$request->user()?->getAuthIdentifier()),
        ]);

        $this->actingAs(new User);

        $this->postJson('/otp/email', ['code' => '000000'])
            ->assertStatus(422);

        $response = $this->postJson('/otp/email', ['code' => '000000']);

        $response->assertStatus(429);
        $response->assertHeader('Retry-After', 60);
        $response->assertExactJson([
            'message' => trans('otp::messages.'.Otp::THROTTLE, ['seconds' => 60]),
        ]);
    }

    public function test_limit_response_callback_is_used()
    {
        $this->actingAs(new User);

        $this->postJson('/otp/email', ['code' => '000000'])
            ->assertStatus(422);

        $this->postJson('/otp/email', ['code' => '000000'])
            ->assertStatus(429)
            ->assertSeeText('custom throttled');
    }
}