<?php

namespace Codewiser\Otp\Tests;

use Carbon\Carbon;
use Codewiser\Otp\RateLimiter\OtpRateLimiter;
use Codewiser\Otp\Tests\Fakes\User;
use Codewiser\Otp\Tests\Fakes\UserProvider;
use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;

class OtpCountdownTest extends TestCase
{
    protected User $user;

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $this->user = new User;
        $this->user->email = 'user@example.com';

        $app['auth']->provider('otp-countdown-test', fn () => new UserProvider([
            $this->user->email => $this->user,
        ]));
        $app['config']->set('auth.providers.users.driver', 'otp-countdown-test');
        $app['auth']->forgetGuards();

        $app->make(RateLimiter::class)->for(OtpRateLimiter::ISSUE, fn (Request $request) => [
            Limit::perMinute(1)->by('countdown'),
        ]);

        $app->make(RateLimiter::class)->for(OtpRateLimiter::VERIFY, fn (Request $request) => [
            Limit::perMinute(1)->by('countdown'),
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Freeze the clock, so that the retry-after of a freshly hit limit is
        // not affected by a second boundary crossed mid-test.
        Carbon::setTestNow(Carbon::now());
    }

    private function issue(): string
    {
        $this->post('/otp/login', ['email' => 'user@example.com', 'send' => '']);

        return $this->user->sentOtps[array_key_last($this->user->sentOtps)];
    }

    private function guess(string $code)
    {
        return $this->post('/otp/login', ['email' => 'user@example.com', 'code' => $code]);
    }

    private function throttleVerification()
    {
        $this->issue();

        $this->guess('000000')->assertSessionHasErrors('code');
        $this->guess('000000')->assertSessionHasErrors('code');
    }

    public function test_login_view_counts_down_to_the_next_code()
    {
        $this->markTestIncomplete(
            'Known issue: otp::fragments.countdown is not included by the shipped views.'
        );

        $this->post('/otp/login', ['email' => 'user@example.com', 'send' => ''])
            ->assertRedirect();

        $response = $this->get('/otp/login');

        $response->assertOk();
        $response->assertSee('data-otp-countdown', false);
        $response->assertSee('data-otp-countdown-seconds="60"', false);
        $response->assertSee('vendor/otp/countdown.js', false);
    }

    public function test_login_view_has_no_countdown_when_a_code_may_be_requested()
    {
        $this->get('/otp/login')
            ->assertOk()
            ->assertDontSee('data-otp-countdown', false);
    }

    public function test_the_views_load_the_countdown_script()
    {
        $this->get('/otp/login')
            ->assertOk()
            ->assertSee('vendor/otp/countdown.js', false);

        $this->actingAs($this->user)
            ->get('/otp/email')
            ->assertOk()
            ->assertSee('vendor/otp/countdown.js', false);
    }

    public function test_throttled_verification_message_is_rendered_for_the_countdown()
    {
        $this->throttleVerification();

        // The script counts a number down in a .invalid message that sits next
        // to an input, so both have to be rendered, in that order.
        $this->get('/otp/login')
            ->assertOk()
            ->assertSeeInOrder([
                'name="code"',
                '<div class="invalid">',
                'Too many attempts. Please try again in 60 seconds.',
            ], false);
    }

    public function test_a_message_without_a_number_is_left_to_the_script()
    {
        $this->issue();

        $this->guess('000000')->assertSessionHasErrors('code');

        // Carries no digit, so the script has nothing to count down.
        $this->get('/otp/login')
            ->assertOk()
            ->assertSee(
                '<div class="invalid">One time password does not match our records.</div>',
                false
            );
    }

    public function test_throttled_email_verification_message_is_rendered_for_the_countdown()
    {
        $this->actingAs($this->user)
            ->post('/otp/email', ['send' => ''])
            ->assertRedirect();

        $this->actingAs($this->user)
            ->post('/otp/email', ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->actingAs($this->user)
            ->post('/otp/email', ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->actingAs($this->user)
            ->get('/otp/email')
            ->assertOk()
            ->assertSeeInOrder([
                'name="code"',
                '<div class="invalid">',
                'Too many attempts. Please try again in 60 seconds.',
            ], false);
    }

    public function test_verify_email_view_counts_down_to_the_next_code()
    {
        $this->markTestIncomplete(
            'Known issue: otp::fragments.countdown is not included by the shipped views.'
        );

        $user = new User;

        $this->actingAs($user)
            ->post('/otp/email', ['send' => ''])
            ->assertRedirect();

        $this->actingAs($user)
            ->get('/otp/email')
            ->assertOk()
            ->assertSee('data-otp-countdown', false)
            ->assertSee('vendor/otp/countdown.js', false);
    }
}