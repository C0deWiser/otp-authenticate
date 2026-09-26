<?php

namespace Codewiser\Otp\Tests;

use Codewiser\Otp\Http\Middleware\EnsureOtpIsPassed;
use Codewiser\Otp\Otp;
use Codewiser\Otp\Tests\Fakes\Guard;
use Codewiser\Otp\Tests\Fakes\PlainUser;
use Codewiser\Otp\Tests\Fakes\User;
use Codewiser\Otp\Tests\Fakes\UserProvider;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

class EnsureOtpIsPassedTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(Otp::class, new Otp(new UserProvider, new Guard));

        Route::middleware(['web', EnsureOtpIsPassed::class])
            ->get('/otp-protected', fn () => 'protected content');

        Route::middleware('web')
            ->post('/otp-protected/logout', function () {
                Auth::logout();

                return 'logged out';
            });
    }

    public function test_redirects_user_with_outdated_email_verification()
    {
        $user = new User;
        $user->emailVerifiedAt = Carbon::now()->subWeeks(2);

        $this->actingAs($user)
            ->get('/otp-protected')
            ->assertRedirect('/otp/email');

        $this->assertSame(Otp::SENT, session('status'));
        $this->assertCount(1, $user->sentOtps);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $user->sentOtps[0]);
    }

    public function test_lets_user_in_when_otp_was_passed()
    {
        $user = new User;
        $user->emailVerifiedAt = Carbon::now()->subWeeks(2);

        $this->actingAs($user)
            ->withSession(['otp_passed:id:1' => true])
            ->get('/otp-protected')
            ->assertOk()
            ->assertSee('protected content');
    }

    public function test_lets_user_in_when_email_verification_is_still_valid()
    {
        $user = new User;
        $user->emailVerified = true;
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

    public function test_another_user_on_the_same_session_has_to_revalidate()
    {
        $first = new User(1);
        $first->emailVerifiedAt = Carbon::now()->subWeeks(2);

        $this->actingAs($first)
            ->withSession(['otp_passed:id:1' => true])
            ->get('/otp-protected')
            ->assertOk();

        $this->post('/otp-protected/logout');

        $second = new User(2);
        $second->email = 'second@example.com';
        $second->emailVerifiedAt = Carbon::now()->subWeeks(2);

        $this->actingAs($second)
            ->get('/otp-protected')
            ->assertRedirect('/otp/email');

        $this->assertCount(1, $second->sentOtps);
    }
}