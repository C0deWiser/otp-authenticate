<?php

namespace Codewiser\Otp\Tests;

use Codewiser\Otp\OtpVerify;
use Codewiser\Otp\Tests\Fakes\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class RevalidateServiceTest extends TestCase
{
    private function service(?string $cooldown = null): OtpVerify
    {
        return new OtpVerify($cooldown);
    }

    public function test_not_needed_for_user_outside_contract()
    {
        $this->assertFalse($this->service()->needToVerifyEmail(new \stdClass));
    }

    public function test_needed_when_email_is_not_verified()
    {
        $user = new User;
        $user->emailVerifiedAt = null;

        $this->assertTrue($this->service()->needToVerifyEmail($user));
    }

    public function test_needed_when_cooldown_is_disabled()
    {
        $user = new User;
        $user->emailVerifiedAt = Carbon::now();

        $this->assertTrue($this->service()->needToVerifyEmail($user));
    }

    public function test_not_needed_when_verified_recently()
    {
        $user = new User;
        $user->emailVerifiedAt = Carbon::now();

        $this->assertFalse($this->service('P1W')->needToVerifyEmail($user));
    }

    public function test_needed_when_verification_is_outdated()
    {
        $user = new User;
        $user->emailVerifiedAt = Carbon::now()->subWeeks(2);

        $this->assertTrue($this->service('P1W')->needToVerifyEmail($user));
    }

    public function test_verification_timestamp_object_is_not_mutated()
    {
        $user = new User;
        $user->emailVerifiedAt = $verifiedAt = Carbon::now();

        $this->service('P1W')->needToVerifyEmail($user);

        $this->assertTrue($verifiedAt->equalTo($user->emailVerifiedAt));
    }

    public function test_notice_view_uses_default_template()
    {
        $view = $this->service('P1W')->view(Request::create('/email/otp'), 0);

        $this->assertSame('otp::verify-email', $view->name());
    }

    public function test_notice_view_using_overrides_template()
    {
        OtpVerify::verifyEmailRequestView('otp::login');

        $view = $this->service('P1W')->view(Request::create('/email/otp'), 0);

        $this->assertSame('otp::login', $view->name());
    }
}