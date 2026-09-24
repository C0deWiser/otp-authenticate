<?php

namespace Codewiser\Otp\Tests;

use Codewiser\Otp\Otp;
use Codewiser\Otp\OtpVerify;
use Codewiser\Otp\RateLimiter\OtpRateLimiter;
use Codewiser\Otp\Tests\Fakes\User;
use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;

class RevalidateControllerTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app->instance(OtpVerify::class, new OtpVerify('P1W'));

        $app->make(RateLimiter::class)->for(OtpRateLimiter::ISSUE, fn (Request $request) => [
            Limit::perMinute(30)->by($request->user()?->getAuthIdentifier() ?: 'guest'),
        ]);

        $app->make(RateLimiter::class)->for(OtpRateLimiter::VERIFY, fn (Request $request) => [
            Limit::perMinute(30)->by($request->user()?->getAuthIdentifier() ?: 'guest'),
        ]);
    }

    public function test_notice_route_renders_the_form()
    {
        $this->actingAs(new User)
            ->get('/email/otp')
            ->assertOk()
            ->assertSee('One time password');
    }

    public function test_notice_route_redirects_when_otp_was_passed()
    {
        $this->actingAs(new User)
            ->withSession(['otp_passed' => true])
            ->get('/email/otp')
            ->assertRedirect('/');
    }

    public function test_notice_route_returns_no_content_for_json()
    {
        $this->actingAs(new User)
            ->getJson('/email/otp')
            ->assertStatus(204);
    }

    public function test_issue_sends_a_code()
    {
        $user = new User;

        $this->actingAs($user)
            ->post('/email/otp')
            ->assertRedirect();

        $this->assertSame(Otp::OTP_SENT, session('status'));
        $this->assertCount(1, $user->sentOtps);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $user->sentOtps[0]);
    }

    public function test_verify_accepts_the_right_code()
    {
        $user = new User;

        $this->actingAs($user);
        $this->post('/email/otp');

        $code = $user->sentOtps[0];

        $this->put('/email/otp', ['otp' => $code])
            ->assertRedirect('/');

        $this->assertTrue(session()->get('otp_passed'));
        $this->assertTrue($user->emailVerified);
    }

    public function test_verify_rejects_a_wrong_code()
    {
        $user = new User;

        $this->actingAs($user);
        $this->post('/email/otp');

        $this->put('/email/otp', ['otp' => '000000'])
            ->assertSessionHasErrors('otp');

        $this->assertFalse(session()->get('otp_passed', false));
        $this->assertFalse($user->emailVerified);
    }

    public function test_verify_sends_a_new_code_when_code_is_lost()
    {
        $user = new User;

        $this->actingAs($user)
            ->put('/email/otp', ['otp' => '123456'])
            ->assertSessionHasErrors('otp');

        $this->assertCount(1, $user->sentOtps);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $user->sentOtps[0]);
    }
}