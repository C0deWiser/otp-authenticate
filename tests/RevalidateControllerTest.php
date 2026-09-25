<?php

namespace Codewiser\Otp\Tests;

use Codewiser\Otp\Otp;
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

        $app->instance(Otp::class, new Otp);

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
            ->get('/otp/email')
            ->assertOk()
            ->assertSee('One time password');
    }

    public function test_notice_route_redirects_when_otp_was_passed()
    {
        $this->actingAs(new User)
            ->withSession(['otp_passed' => true])
            ->get('/otp/email')
            ->assertRedirect('/');
    }

    public function test_notice_route_returns_no_content_for_json()
    {
        $this->actingAs(new User)
            ->getJson('/otp/email')
            ->assertStatus(204);
    }

    public function test_issue_sends_a_code()
    {
        $user = new User;

        $this->actingAs($user)
            ->post('/otp/email')
            ->assertRedirect();

        $this->assertSame(Otp::SENT, session('status'));
        $this->assertCount(1, $user->sentOtps);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $user->sentOtps[0]);
    }

    public function test_issue_sends_a_code_as_json()
    {
        $user = new User;

        $this->actingAs($user)
            ->postJson('/otp/email')
            ->assertOk()
            ->assertJson(['message' => Otp::SENT]);

        $this->assertCount(1, $user->sentOtps);
    }

    public function test_verify_accepts_the_right_code()
    {
        $user = new User;

        $this->actingAs($user);
        $this->post('/otp/email');

        $code = $user->sentOtps[0];

        $this->put('/otp/email', ['code' => $code])
            ->assertRedirect();

        $this->assertTrue(session()->get('otp_passed'));
        $this->assertTrue($user->emailVerified);
    }

    public function test_verify_accepts_the_right_code_as_json()
    {
        $user = new User;

        $this->actingAs($user);
        $this->post('/otp/email');

        $code = $user->sentOtps[0];

        $this->actingAs($user)
            ->putJson('/otp/email', ['code' => $code])
            ->assertOk();

        $this->assertTrue(session()->get('otp_passed'));
        $this->assertTrue($user->emailVerified);
    }

    public function test_verify_rejects_a_wrong_code()
    {
        $user = new User;

        $this->actingAs($user);
        $this->post('/otp/email');

        $this->put('/otp/email', ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertFalse(session()->get('otp_passed', false));
        $this->assertFalse($user->emailVerified);
    }

    public function test_verify_sends_a_new_code_when_code_is_lost()
    {
        $user = new User;

        $this->actingAs($user)
            ->put('/otp/email', ['code' => '123456'])
            ->assertSessionHasErrors('code');

        $this->assertCount(1, $user->sentOtps);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $user->sentOtps[0]);
    }
}