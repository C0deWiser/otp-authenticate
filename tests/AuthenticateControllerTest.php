<?php

namespace Codewiser\Otp\Tests;

use Codewiser\Otp\Otp;
use Codewiser\Otp\RateLimiter\OtpRateLimiter;
use Codewiser\Otp\Tests\Fakes\User;
use Codewiser\Otp\Tests\Fakes\UserProvider;
use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;

class AuthenticateControllerTest extends TestCase
{
    protected User $user;

    protected User $victim;

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $this->user = new User;
        $this->user->email = 'user@example.com';

        $this->victim = new User(2);
        $this->victim->email = 'victim@example.com';

        $app['auth']->provider('otp-login-test', fn () => new UserProvider([
            $this->user->email   => $this->user,
            $this->victim->email => $this->victim,
        ]));
        $app['config']->set('auth.providers.users.driver', 'otp-login-test');
        $app['auth']->forgetGuards();

        $app->make(RateLimiter::class)->for(OtpRateLimiter::ISSUE, fn (Request $request) => [
            Limit::perMinute(30)->by($request->input('email') ?: $request->ip()),
        ]);

        $app->make(RateLimiter::class)->for(OtpRateLimiter::VERIFY, fn (Request $request) => [
            Limit::perMinute(30)->by($request->input('email') ?: $request->ip()),
        ]);
    }

    public function test_login_route_returns_no_content_for_json()
    {
        $this->getJson('/otp/login')
            ->assertStatus(204);
    }

    public function test_issue_sends_a_code_to_the_resolved_user()
    {
        $this->post('/otp/login', ['email' => 'user@example.com', 'send' => ''])
            ->assertRedirect();

        $this->assertSame(Otp::SENT, session('status'));
        $this->assertCount(1, $this->user->sentOtps);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $this->user->sentOtps[0]);
    }

    public function test_issue_sends_a_code_as_json()
    {
        $this->postJson('/otp/login', ['email' => $this->user->email, 'send' => ''])
            ->assertOk()
            ->assertJson(['message' => Otp::SENT]);

        $this->assertCount(1, $this->user->sentOtps);
    }

    public function test_issue_is_silent_for_unknown_email()
    {
        $this->post('/otp/login', ['email' => 'ghost@example.com', 'send' => ''])
            ->assertRedirect()
            ->assertSessionHas('status', Otp::SENT);
    }

    public function test_verify_logs_the_guest_in()
    {
        $this->post('/otp/login', ['email' => $this->user->email, 'send' => '']);

        $code = $this->user->sentOtps[0];

        $this->post('/otp/login', ['email' => $this->user->email, 'code' => $code])
            ->assertRedirect();

        $this->assertAuthenticatedAs($this->user);
        $this->assertTrue($this->user->emailVerified);
        $this->assertSame(['otp_passed:id:1' => true], $this->otpPassed());
    }

    public function test_verify_logs_the_guest_in_as_json()
    {
        $this->post('/otp/login', ['email' => $this->user->email, 'send' => '']);

        $code = $this->user->sentOtps[0];

        $this->postJson('/otp/login', ['email' => $this->user->email, 'code' => $code])
            ->assertOk();

        $this->assertAuthenticatedAs($this->user);
        $this->assertSame(['otp_passed:id:1' => true], $this->otpPassed());
    }

    public function test_verify_rejects_unknown_email()
    {
        $this->post('/otp/login', ['email' => $this->user->email, 'send' => '']);

        $code = $this->user->sentOtps[0];

        $this->post('/otp/login', ['email' => 'ghost@example.com', 'code' => $code])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_verify_rejects_a_code_issued_for_another_user()
    {
        $this->post('/otp/login', ['email' => $this->user->email, 'send' => '']);

        $code = $this->user->sentOtps[0];

        $this->post('/otp/login', ['email' => $this->victim->email, 'code' => $code])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
        $this->assertFalse($this->victim->emailVerified);
        $this->assertSame([], $this->otpPassed());
    }

    public function test_verify_rejects_a_wrong_code()
    {
        $this->post('/otp/login', ['email' => $this->user->email, 'send' => '']);

        $code = $this->user->sentOtps[0];

        // The generated code is random, make sure the guess is not the code.
        $this->post('/otp/login', ['email' => $this->user->email, 'code' => $code === '000000' ? '111111' : '000000'])
            ->assertSessionHasErrors([
                'code' => trans('otp::messages.'.Otp::MISMATCH),
            ]);

        $this->assertGuest();
        $this->assertFalse($this->user->emailVerified);
        $this->assertSame([], $this->otpPassed());
    }

    public function test_verify_reports_a_lost_code()
    {
        $this->post('/otp/login', ['email' => $this->user->email, 'code' => '000000'])
            ->assertSessionHasErrors([
                'code' => trans('otp::messages.'.Otp::LOST),
            ]);

        $this->assertCount(1, $this->user->sentOtps);
        $this->assertGuest();
    }

    public function test_verify_reports_an_unknown_email()
    {
        $this->post('/otp/login', ['email' => $this->user->email, 'send' => '']);

        $this->post('/otp/login', ['email' => 'ghost@example.com', 'code' => $this->user->sentOtps[0]])
            ->assertSessionHasErrors([
                'code' => trans('otp::messages.'.Otp::LOST),
            ]);

        $this->assertGuest();
    }
}