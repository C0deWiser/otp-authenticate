<?php

namespace Codewiser\Otp\Tests;

use Codewiser\Otp\Otp;
use Codewiser\Otp\Tests\Fakes\PlainUser;
use Codewiser\Otp\Tests\Fakes\Session;
use Codewiser\Otp\Tests\Fakes\User;

class RevalidateServiceTest extends TestCase
{
    private function service(): Otp
    {
        return new Otp;
    }

    public function test_send_new_code_sends_when_user_implements_contract()
    {
        $user = new User;
        $session = new Session;

        $result = $this->service()->sendNewCode($session, $user);

        $this->assertSame(Otp::SENT, $result);
        $this->assertCount(1, $user->sentOtps);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $user->sentOtps[0]);
    }

    public function test_send_new_code_fails_for_user_outside_contract()
    {
        $this->expectException(\RuntimeException::class);

        $this->service()->sendNewCode(new Session, new PlainUser(1));
    }
}