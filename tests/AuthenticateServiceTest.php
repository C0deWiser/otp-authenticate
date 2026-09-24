<?php

namespace Codewiser\Otp\Tests;

use Codewiser\Otp\OtpAuthenticate;
use Codewiser\Otp\Otp;
use Codewiser\Otp\Tests\Fakes\PlainUser;
use Codewiser\Otp\Tests\Fakes\Session;
use Codewiser\Otp\Tests\Fakes\User;
use Codewiser\Otp\Tests\Fakes\UserProvider;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AuthenticateServiceTest extends TestCase
{
    private function service(array $users = [], string $key = 'email'): OtpAuthenticate
    {
        return new OtpAuthenticate(new UserProvider($users, $key));
    }

    public function test_send_new_code_stores_six_digit_code_and_notifies()
    {
        $service = $this->service();
        $session = new Session;
        $user = new User;

        $result = $service->sendNewCode($session, $user);

        $this->assertSame(Otp::OTP_SENT, $result);
        $this->assertCount(1, $user->sentOtps);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $user->sentOtps[0]);
    }

    public function test_send_new_code_is_silent_for_unresolvable_user()
    {
        $service = $this->service();
        $session = new Session;

        $result = $service->sendNewCode($session, new \stdClass);

        $this->assertSame(Otp::OTP_SENT, $result);
        $this->assertSame([], $session->all());
    }

    public function test_send_new_code_is_silent_for_user_missing_the_otp_contract()
    {
        $service = $this->service();
        $session = new Session;

        $result = $service->sendNewCode($session, new PlainUser(1));

        $this->assertSame(Otp::OTP_SENT, $result);
        $this->assertSame([], $session->all());
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
            $this->assertSame(Otp::OTP_LOST, $e->errors()['otp'][0]);
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
            $this->assertArrayHasKey('otp', $e->errors());
            $this->assertSame(Otp::OTP_MISMATCH, $e->errors()['otp'][0]);
        }

        $this->assertCount(1, $user->sentOtps);

        $this->assertSame($user, $service->validate($session, $user, $user->sentOtps[0]));
        $this->assertTrue($user->emailVerified);
        $this->assertTrue($session->get('otp_passed'));
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
            $this->assertSame(Otp::OTP_MISMATCH, $e->errors()['otp'][0]);
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
        $this->assertTrue($session->get('otp_passed'));
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

        $this->assertSame(Otp::OTP_SENT, $result);
        $this->assertCount(1, $user->sentOtps);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $user->sentOtps[0]);
    }

    public function test_send_new_code_is_silent_for_unknown_guest_email()
    {
        $service = $this->service();
        $session = new Session;

        $result = $service->sendNewCode($session, 'ghost@example.com');

        $this->assertSame(Otp::OTP_SENT, $result);
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
        $this->assertTrue($session->get('otp_passed'));
    }

    public function test_validate_rejects_unknown_guest_email()
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
            $this->assertArrayHasKey('otp', $e->errors());
            $this->assertSame(Otp::OTP_LOST, $e->errors()['otp'][0]);
        }

        $this->assertFalse($session->get('otp_passed', false));
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
            $this->assertArrayHasKey('otp', $e->errors());
        }

        $this->assertFalse($session->get('otp_passed', false));
    }

    public function test_resolve_user_uses_provider()
    {
        $user = new User;

        $service = $this->service([$user->email => $user]);

        $this->assertSame($user, $service->resolveUser($user->email));
        $this->assertNull($service->resolveUser('missing@example.com'));
        $this->assertNull($service->resolveUser(''));
    }

    public function test_resolve_user_uses_fortify_username()
    {
        $user = new User;

        config(['fortify.email' => 'username']);

        $service = $this->service([$user->username => $user], 'username');

        $this->assertSame($user, $service->resolveUser($user->username));
        $this->assertNull($service->resolveUser('ghost'));
    }

    public function test_login_view_uses_default_template()
    {
        $view = $this->service()->view(Request::create('/login/otp'), 0);

        $this->assertSame('otp::login', $view->name());
    }

    public function test_login_view_using_overrides_template()
    {
        OtpAuthenticate::loginRequestView(fn () => view('otp::verify-email'));

        $view = $this->service()->view(Request::create('/login/otp'), 0);

        $this->assertSame('otp::verify-email', $view->name());
    }
}