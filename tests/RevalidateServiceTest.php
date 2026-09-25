<?php

namespace Codewiser\Otp\Tests;

use Codewiser\Otp\Otp;
use Codewiser\Otp\OtpVerify;
use Codewiser\Otp\Tests\Fakes\PlainUser;
use Codewiser\Otp\Tests\Fakes\Session;
use Codewiser\Otp\Tests\Fakes\User;
use Illuminate\Support\Carbon;

class RevalidateServiceTest extends TestCase
{
    private function service(?\DateInterval $cooldown = null): OtpVerify
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

        $this->assertFalse($this->service(new \DateInterval('P1W'))->needToVerifyEmail($user));
    }

    public function test_needed_when_verification_is_outdated()
    {
        $user = new User;
        $user->emailVerifiedAt = Carbon::now()->subWeeks(2);

        $this->assertTrue($this->service(new \DateInterval('P1W'))->needToVerifyEmail($user));
    }

    public function test_verification_timestamp_object_is_not_mutated()
    {
        $user = new User;
        $user->emailVerifiedAt = $verifiedAt = Carbon::now();

        $this->service(new \DateInterval('P1W'))->needToVerifyEmail($user);

        $this->assertTrue($verifiedAt->equalTo($user->emailVerifiedAt));
    }

    public function test_resolve_user_keeps_authenticatable()
    {
        $user = new User;

        $this->assertSame($user, $this->service()->resolveUser($user));
        $this->assertNull($this->service()->resolveUser(new \stdClass));
    }

    public function test_send_new_code_sends_when_user_implements_contract()
    {
        $user = new User;
        $session = new Session;

        $result = $this->service()->sendNewCode($session, $user);

        $this->assertSame(Otp::OTP_SENT, $result);
        $this->assertCount(1, $user->sentOtps);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $user->sentOtps[0]);
    }

    public function test_send_new_code_fails_for_user_outside_contract()
    {
        $this->expectException(\RuntimeException::class);

        $this->service()->sendNewCode(new Session, new PlainUser(1));
    }
}