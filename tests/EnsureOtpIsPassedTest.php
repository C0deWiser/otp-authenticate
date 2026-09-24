<?php

namespace Codewiser\Otp\Tests;

use Codewiser\Otp\Http\Middleware\EnsureOtpIsPassed;
use Codewiser\Otp\Otp;
use Codewiser\Otp\OtpVerify;
use Codewiser\Otp\Tests\Fakes\PlainUser;
use Codewiser\Otp\Tests\Fakes\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;

class EnsureOtpIsPassedTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(OtpVerify::class, new OtpVerify('P1W'));

        Route::middleware(['web', EnsureOtpIsPassed::class])
            ->get('/otp-protected', fn () => 'protected content');
    }

    public function test_redirects_user_with_outdated_email_verification()
    {
        $user = new User;
        $user->emailVerifiedAt = Carbon::now()->subWeeks(2);

        $this->actingAs($user)
            ->get('/otp-protected')
            ->assertRedirect('/email/otp');

        $this->assertSame(Otp::OTP_SENT, session('status'));
        $this->assertCount(1, $user->sentOtps);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $user->sentOtps[0]);
    }

    public function test_lets_user_in_when_otp_was_passed()
    {
        $user = new User;
        $user->emailVerifiedAt = Carbon::now()->subWeeks(2);

        $this->actingAs($user)
            ->withSession(['otp_passed' => true])
            ->get('/otp-protected')
            ->assertOk()
            ->assertSee('protected content');
    }

    public function test_lets_user_in_when_email_was_verified_recently()
    {
        $user = new User;
        $user->emailVerifiedAt = Carbon::now();

        $this->actingAs($user)
            ->get('/otp-protected')
            ->assertOk()
            ->assertSee('protected content');

        $this->assertCount(0, $user->sentOtps);
    }

    public function test_lets_user_in_when_contract_is_not_applied()
    {
        $this->actingAs(new PlainUser(1))
            ->get('/otp-protected')
            ->assertOk()
            ->assertSee('protected content');
    }

    public function test_aborts_with_403_for_json_requests()
    {
        $user = new User;
        $user->emailVerifiedAt = Carbon::now()->subWeeks(2);

        $this->actingAs($user)
            ->getJson('/otp-protected')
            ->assertStatus(403);
    }
}