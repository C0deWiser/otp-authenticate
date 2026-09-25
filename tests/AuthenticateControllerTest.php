<?php

namespace Codewiser\Otp\Tests;

use Codewiser\Otp\Otp;
use Codewiser\Otp\OtpAuthenticate;
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

        $app->singleton(OtpAuthenticate::class, function ($app) {
            return new OtpAuthenticate(
                $app['auth']->createUserProvider('users'),
                $app['auth']->guard('web')
            );
        });

        $app->make(RateLimiter::class)->for(OtpRateLimiter::ISSUE, fn (Request $request) => [
            Limit::perMinute(30)->by($request->input('email') ?: $request->ip()),
        ]);

        $app->make(RateLimiter::class)->for(OtpRateLimiter::VERIFY, fn (Request $request) => [
            Limit::perMinute(30)->by($request->input('email') ?: $request->ip()),
        ]);
    }

    public function test_login_route_renders_the_form()
    {
        $this->get('/otp/login')
            ->assertOk()
            ->assertSee('One time password');
    }

    public function test_login_route_returns_no_content_for_json()
    {
        $this->getJson('/otp/login')
            ->assertStatus(204);
    }

    public function test_issue_sends_a_code_to_the_resolved_user()
    {
        $this->post('/otp/login', ['email' => 'user@example.com'])
            ->assertRedirect();

        $this->assertSame(Otp::OTP_SENT, session('status'));
        $this->assertCount(1, $this->user->sentOtps);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $this->user->sentOtps[0]);
    }

    public function test_issue_sends_a_code_as_json()
    {
        $this->postJson('/otp/login', ['email' => $this->user->email])
            ->assertOk()
            ->assertJson(['message' => Otp::OTP_SENT]);

        $this->assertCount(1, $this->user->sentOtps);
    }

    public function test_issue_is_silent_for_unknown_email()
    {
        $this->post('/otp/login', ['email' => 'ghost@example.com'])
            ->assertRedirect()
            ->assertSessionHas('status', Otp::OTP_SENT);
    }

    public function test_verify_logs_the_guest_in()
    {
        $this->post('/otp/login', ['email' => $this->user->email]);

        $code = $this->user->sentOtps[0];

        $this->put('/otp/login', ['email' => $this->user->email, 'code' => $code])
            ->assertRedirect();

        $this->assertAuthenticatedAs($this->user);
        $this->assertTrue($this->user->emailVerified);
        $this->assertTrue(session()->get('otp_passed'));
    }

    public function test_verify_logs_the_guest_in_as_json()
    {
        $this->post('/otp/login', ['email' => $this->user->email]);

        $code = $this->user->sentOtps[0];

        $this->putJson('/otp/login', ['email' => $this->user->email, 'code' => $code])
            ->assertOk();

        $this->assertAuthenticatedAs($this->user);
        $this->assertTrue(session()->get('otp_passed'));
    }

    public function test_verify_rejects_unknown_email()
    {
        $this->post('/otp/login', ['email' => $this->user->email]);

        $code = $this->user->sentOtps[0];

        $this->put('/otp/login', ['email' => 'ghost@example.com', 'code' => $code])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_verify_rejects_a_code_issued_for_another_user()
    {
        $this->post('/otp/login', ['email' => $this->user->email]);

        $code = $this->user->sentOtps[0];

        $this->put('/otp/login', ['email' => $this->victim->email, 'code' => $code])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
        $this->assertFalse($this->victim->emailVerified);
        $this->assertFalse(session()->get('otp_passed', false));
    }

    public function test_verify_rejects_a_wrong_code()
    {
        $this->post('/otp/login', ['email' => $this->user->email]);

        $this->put('/otp/login', ['email' => $this->user->email, 'code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
        $this->assertFalse($this->user->emailVerified);
    }
}