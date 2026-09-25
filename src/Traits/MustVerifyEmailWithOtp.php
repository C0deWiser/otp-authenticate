<?php

namespace Codewiser\Otp\Traits;

use Codewiser\Otp\Notifications\OtpNotification;
use DateTimeInterface;
use Illuminate\Auth\MustVerifyEmail;

trait MustVerifyEmailWithOtp
{
    use MustVerifyEmail;

    public function shouldVerifyEmail(): bool
    {
        return ! $this->hasVerifiedEmail();
    }

    public function sendOtpNotification(string $code): void
    {
        $this->notify(new OtpNotification($code));
    }
}