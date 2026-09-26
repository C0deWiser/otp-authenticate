<?php

namespace Codewiser\Otp\Tests;

use Codewiser\Otp\Otp;
use Codewiser\Otp\Tests\Fakes\Guard;
use Codewiser\Otp\Tests\Fakes\PlainUser;
use Codewiser\Otp\Tests\Fakes\Session;
use Codewiser\Otp\Tests\Fakes\User;
use Codewiser\Otp\Tests\Fakes\UserProvider;
use Illuminate\Validation\ValidationException;

class AuthenticateServiceTest extends TestCase
{
    private function service(array $users = [], string $key = 'email'): Otp
    {
        return new Otp(new UserProvider($users, $key), new Guard);
    }

    public function test_send_new_code_stores_six_digit_code_and_notifies()
    {
        $service = $this->service();
        $session = new Session;
        $user = new User;

        $result = $service->sendNewCode($session, $user);

        $this->assertSame(Otp::SENT, $result);
        $this->assertCount(1, $user->sentOtps);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $user->sentOtps[0]);
    }

    public function test_send_new_code_is_silent_for_unresolvable_user()
    {
        $service = $this->service();
        $session = new Session;

        $result = $service->sendNewCode($session, new \stdClass);

        $this->assertSame(Otp::SENT, $result);
        $this->assertSame([], $session->all());
    }

    public function test_send_new_code_is_silent_for_user_missing_the_otp_contract()
    {
        $service = $this->service();
        $session = new Session;

        $result = $service->sendNewCode($session, new PlainUser(1));

        $this->assertSame(Otp::SENT, $result);
        $this->assertSame([], $session->all());
    }

    public function test_send_new_code_is_silent_for_resolved_user_missing_the_otp_contract()
    {
        $user = new PlainUser(1);

        $service = $this->service([$user->id => $user], 'id');
        $session = new Session;

        $result = $service->sendNewCode($session, $user->id);

        $this->assertSame(Otp::SENT, $result);
        $this->assertSame([], $session->all());
    }

    public function test_passed_and_not_passed()
    {
        $service = $this->service();
        $session = new Session;
        $user = new User;

        $this->assertFalse($service->passed($session, $user));
        $this->assertTrue($service->notPassed($session, $user));

        $service->authenticate($session, $user, false);

        $this->assertTrue($service->passed($session, $user));
        $this->assertFalse($service->notPassed($session, $user));
    }

    public function test_another_user_did_not_pass_the_otp()
    {
        $service = $this->service();
        $session = new Session;

        $service->authenticate($session, new User(1), false);

        $this->assertTrue($service->passed($session, new User(1)));
        $this->assertFalse($service->passed($session, new User(2)));
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
            $this->assertArrayHasKey('code', $e->errors());
            $this->assertSame(trans('otp::messages.'.Otp::LOST), $e->errors()['code'][0]);
        }

        $this->assertCount(1, $user->sentOtps);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $user->sentOtps[0]);
    }

    public function test_validate_rejects_wrong_code_without_resending()
    {
        $service = $this->service();
        $session = new Session;
        $user = new User;

        $service->sendNewCode($session, $user);

        $wrongCode = $user->sentOtps[0] === '999999' ? '000000' : '999999';

        try {
            $service->validate($session, $user, $wrongCode);
            $this->fail('ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('code', $e->errors());
            $this->assertSame(trans('otp::messages.'.Otp::MISMATCH), $e->errors()['code'][0]);
        }

        $this->assertCount(1, $user->sentOtps);

        $this->assertSame($user, $service->validate($session, $user, $user->sentOtps[0]));
        $this->assertTrue($user->emailVerified);
        $this->assertSame(['otp_passed:id:1' => true], $this->otpPassed($session));
    }

    public function test_validate_uses_strict_comparison()
    {
        Otp::newCodeUsing(fn () => '100000');

        $service = $this->service();
        $session = new Session;
        $user = new User;

        $service->sendNewCode($session, $user);

        try {
            $service->validate($session, $user, '1e5');
            $this->fail('ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertSame(trans('otp::messages.'.Otp::MISMATCH), $e->errors()['code'][0]);
        }
    }

    public function test_validate_marks_email_verified_and_session_passed()
    {
        Otp::newCodeUsing(fn () => '123456');

        $service = $this->service();
        $session = new Session;
        $user = new User;

        $service->sendNewCode($session, $user);

        $service->validate($session, $user, '123456');

        $this->assertTrue($user->emailVerified);
        $this->assertSame(['otp_passed:id:1' => true], $this->otpPassed($session));
    }

    public function test_validate_returns_the_authenticated_user()
    {
        Otp::newCodeUsing(fn () => '123456');

        $service = $this->service();
        $session = new Session;
        $user = new User;

        $service->sendNewCode($session, $user);

        $this->assertSame($user, $service->validate($session, $user, '123456'));
    }

    public function test_send_new_code_resolves_guest_email_and_notifies()
    {
        $user = new User;

        $service = $this->service([$user->email => $user]);
        $session = new Session;

        $result = $service->sendNewCode($session, $user->email);

        $this->assertSame(Otp::SENT, $result);
        $this->assertCount(1, $user->sentOtps);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $user->sentOtps[0]);
    }

    public function test_send_new_code_is_silent_for_unknown_guest_email()
    {
        $service = $this->service();
        $session = new Session;

        $result = $service->sendNewCode($session, 'ghost@example.com');

        $this->assertSame(Otp::SENT, $result);
        $this->assertSame([], $session->all());
    }

    public function test_validate_resolves_guest_email_and_marks_verified()
    {
        Otp::newCodeUsing(fn () => '123456');

        $user = new User;

        $service = $this->service([$user->email => $user]);
        $session = new Session;

        $service->sendNewCode($session, $user->email);

        $resolved = $service->validate($session, $user->email, '123456');

        $this->assertSame($user, $resolved);
        $this->assertTrue($user->emailVerified);
        $this->assertSame(['otp_passed:id:1' => true], $this->otpPassed($session));
    }

    public function test_validate_throws_lost_error_for_unknown_guest_email()
    {
        Otp::newCodeUsing(fn () => '123456');

        $user = new User;

        $service = $this->service([$user->email => $user]);
        $session = new Session;

        $service->sendNewCode($session, $user->email);

        try {
            $service->validate($session, 'guest@example.com', '123456');
            $this->fail('ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('code', $e->errors());
            $this->assertSame(trans('otp::messages.'.Otp::LOST), $e->errors()['code'][0]);
        }

        $this->assertSame([], $this->otpPassed($session));
    }

    public function test_validate_fails_when_code_is_right_but_user_is_gone()
    {
        Otp::newCodeUsing(fn () => '123456');

        $issuingService = $this->service([
            'user@example.com' => new User,
        ]);
        $session = new Session;

        $issuingService->sendNewCode($session, 'user@example.com');

        // Same session (the code is stored there), but the user can no longer
        // be resolved (e.g. account was removed between sending and verifying).
        $service = $this->service();

        try {
            $service->validate($session, 'user@example.com', '123456');
            $this->fail('ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('email', $e->errors());
            $this->assertSame(trans('otp::messages.'.Otp::USER), $e->errors()['email'][0]);
        }

        $this->assertSame([], $this->otpPassed($session));
    }

    public function test_validate_rejects_code_issued_for_another_user()
    {
        $attacker = new User(1);
        $attacker->email = 'attacker@example.com';

        $victim = new User(2);
        $victim->email = 'victim@example.com';

        $service = $this->service([
            $attacker->email => $attacker,
            $victim->email   => $victim,
        ]);

        $session = new Session;

        $service->sendNewCode($session, $attacker->email);

        $code = $attacker->sentOtps[0];

        try {
            $service->validate($session, $victim->email, $code);
            $this->fail('ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('code', $e->errors());
        }

        $this->assertSame([], $this->otpPassed($session));
    }

    public function test_authenticate_logs_the_user_in_and_marks_as_passed()
    {
        $guard = new Guard;
        $service = new Otp(new UserProvider, $guard);
        $session = new Session;
        $user = new User;

        $service->authenticate($session, $user, true);

        $this->assertSame([$user], $guard->logins);
        $this->assertSame($user, $guard->user);
        $this->assertSame(['otp_passed:id:1' => true], $this->otpPassed($session));
    }

    public function test_authenticate_does_not_mark_as_passed_when_session_is_not_regenerated()
    {
        $session = new class extends Session {
            public function regenerate($destroy = false): bool
            {
                return false;
            }
        };

        $service = new Otp(new UserProvider, new Guard);

        $service->authenticate($session, new User, false);

        $this->assertSame([], $this->otpPassed($session));
    }
}