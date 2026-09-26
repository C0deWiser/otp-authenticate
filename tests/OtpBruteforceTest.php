<?php

namespace Codewiser\Otp\Tests;

use Carbon\Carbon;
use Codewiser\Otp\Otp;
use Codewiser\Otp\RateLimiter\OtpRateLimiter;
use Codewiser\Otp\Tests\Fakes\User;
use Codewiser\Otp\Tests\Fakes\UserProvider;
use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

class OtpBruteforceTest extends TestCase
{
    protected User $user;

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $this->user = new User;
        $this->user->email = 'user@example.com';

        $app['auth']->provider('otp-bruteforce-test', fn () => new UserProvider([
            $this->user->email => $this->user,
        ]));
        $app['config']->set('auth.providers.users.driver', 'otp-bruteforce-test');
        $app['auth']->forgetGuards();

        // Scoped the way the published service provider stub does, including
        // the authenticated user, which changes in the middle of a login.
        $throttleKey = fn (Request $request) => implode('|', array_filter([
            $request->user()?->getAuthIdentifier(),
            $request->input('email') ?: $request->input('code'),
            $request->ip(),
        ], fn($value) => $value !== null && $value !== ''));

        $app->make(RateLimiter::class)->for(OtpRateLimiter::ISSUE, fn (Request $request) => [
            Limit::perMinute(50)->by('issue:'.$throttleKey($request)),
        ]);

        $app->make(RateLimiter::class)->for(OtpRateLimiter::VERIFY, fn (Request $request) => [
            Limit::perMinute(5)->by('verify:'.$throttleKey($request)),
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Freeze the clock, so that the retry-after of a freshly hit limit is
        // not affected by a second boundary crossed mid-test.
        Carbon::setTestNow(Carbon::now());

        Route::middleware('web')->post('/otp-bruteforce/logout', function () {
            Auth::logout();

            return 'logged out';
        });
    }

    private function verifyLimiter(): OtpRateLimiter
    {
        return OtpRateLimiter::for(OtpRateLimiter::VERIFY, Request::create('/otp/login', 'POST', [
            'email' => $this->user->email,
        ]));
    }

    private function issue(): string
    {
        $this->post('/otp/login', ['email' => $this->user->email, 'send' => '']);

        return $this->user->sentOtps[array_key_last($this->user->sentOtps)];
    }

    private function guess(string $code)
    {
        return $this->post('/otp/login', ['email' => $this->user->email, 'code' => $code]);
    }

    public function test_failed_verification_counts_toward_the_bruteforce_limit()
    {
        $this->issue();

        $limiter = $this->verifyLimiter();

        $this->guess('000000')->assertSessionHasErrors('code');

        $this->assertSame(1, $limiter->attempts());
    }

    public function test_missing_code_counts_toward_the_bruteforce_limit()
    {
        $this->issue();

        $this->post('/otp/login', ['email' => $this->user->email])
            ->assertSessionHasErrors('code');

        $this->assertSame(1, $this->verifyLimiter()->attempts());
    }

    public function test_failed_verification_is_throttled_after_the_configured_number_of_attempts()
    {
        $this->issue();

        for ($i = 0; $i < 5; $i++) {
            $this->guess('000000')->assertSessionHasErrors('code');
        }

        $this->guess('000000')
            ->assertSessionHasErrors([
                'code' => trans('otp::messages.'.Otp::THROTTLE, ['seconds' => 60]),
            ]);

        $this->assertCount(1, $this->user->sentOtps);
    }

    public function test_throttled_verification_does_not_grant_access_with_the_right_code()
    {
        $code = $this->issue();

        for ($i = 0; $i < 5; $i++) {
            $this->guess('000000');
        }

        $this->guess($code)->assertSessionHasErrors('code');

        $this->assertGuest();
        $this->assertFalse($this->user->emailVerified);
        $this->assertSame([], $this->otpPassed());
    }

    public function test_successful_verification_resets_the_bruteforce_limit()
    {
        $code = $this->issue();

        $this->guess('000000');
        $this->guess('000000');

        $this->guess($code)->assertRedirect();

        $this->assertAuthenticatedAs($this->user);
        $this->assertSame(0, $this->verifyLimiter()->attempts());
    }

    public function test_repeated_successful_logins_are_not_throttled()
    {
        $limiter = $this->verifyLimiter();

        for ($i = 0; $i < 6; $i++) {
            $this->post('/otp-bruteforce/logout');

            $this->guess($this->issue())->assertRedirect();
        }

        $this->assertFalse($limiter->tooManyAttempts());
        $this->assertAuthenticatedAs($this->user);
    }

    public function test_failed_issue_request_counts_toward_the_issue_limit()
    {
        $limiter = OtpRateLimiter::for(OtpRateLimiter::ISSUE, Request::create('/otp/login', 'POST', [
            'email' => 'not-an-email',
        ]));

        $this->post('/otp/login', ['email' => 'not-an-email', 'send' => ''])
            ->assertSessionHasErrors('email');

        $this->assertSame(1, $limiter->attempts());
    }
}