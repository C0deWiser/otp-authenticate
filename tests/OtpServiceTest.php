<?php

namespace Codewiser\Otp\Tests;

use Codewiser\Otp\OtpService;
use Codewiser\Otp\Tests\Fakes\Session;
use Codewiser\Otp\Tests\Fakes\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class OtpServiceTest extends TestCase
{
    private function service(?string $cooldown = null): OtpService
    {
        return new OtpService($cooldown);
    }

    public function test_send_new_code_stores_six_digit_code_and_notifies()
    {
        $service = $this->service();
        $session = new Session;
        $user = new User;

        $result = $service->sendNewCode($session, $user);

        $this->assertSame(OtpService::OTP_SENT, $result);
        $this->assertCount(1, $user->sentOtps);
        $this->assertSame($session->get('otp'), $user->sentOtps[0]);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $session->get('otp'));
    }

    public function test_send_new_code_returns_null_for_plain_user()
    {
        $service = $this->service();
        $session = new Session;

        $result = $service->sendNewCode($session, new \stdClass);

        $this->assertNull($result);
        $this->assertFalse($session->has('otp'));
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

    public function test_passed_and_not_passed()
    {
        $service = $this->service();
        $session = new Session;

        $this->assertFalse($service->passed($session));
        $this->assertTrue($service->notPassed($session));

        $session->put('otp_passed', true);

        $this->assertTrue($service->passed($session));
        $this->assertFalse($service->notPassed($session));
    }

    public function test_validate_sends_new_code_when_code_is_lost()
    {
        $service = $this->service();
        $session = new Session;
        $user = new User;

        try {
            $service->validate($session, $user, '123456');
            $this->fail('ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('otp', $e->errors());
            $this->assertSame(OtpService::OTP_LOST, $e->errors()['otp'][0]);
        }

        $this->assertCount(1, $user->sentOtps);
        $this->assertTrue($session->has('otp'));
    }

    public function test_validate_rejects_wrong_code_without_resending()
    {
        $service = $this->service();
        $session = new Session;
        $session->put('otp', '123456');
        $user = new User;

        try {
            $service->validate($session, $user, '654321');
            $this->fail('ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('otp', $e->errors());
            $this->assertSame(OtpService::OTP_MISMATCH, $e->errors()['otp'][0]);
        }

        $this->assertCount(0, $user->sentOtps);
        $this->assertSame('123456', $session->get('otp'));
    }

    public function test_validate_uses_strict_comparison()
    {
        $service = $this->service();
        $session = new Session;
        $session->put('otp', '100000');
        $user = new User;

        try {
            $service->validate($session, $user, '1e5');
            $this->fail('ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertSame(OtpService::OTP_MISMATCH, $e->errors()['otp'][0]);
        }
    }

    public function test_validate_marks_email_verified_and_session_passed()
    {
        $service = $this->service();
        $session = new Session;
        $session->put('otp', '123456');
        $user = new User;

        $service->validate($session, $user, '123456');

        $this->assertTrue($user->emailVerified);
        $this->assertTrue($session->get('otp_passed'));
        $this->assertFalse($session->has('otp'));
    }
}