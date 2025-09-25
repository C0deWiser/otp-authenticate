<?php

namespace Codewiser\Otp\Traits;

use Codewiser\Otp\Notifications\VerifyEmailWithOtp;
use DateTimeInterface;
use Illuminate\Auth\MustVerifyEmail;

trait MustVerifyEmailWithOtp
{
    use MustVerifyEmail;

    public function getEmailVerifiedAt(): ?DateTimeInterface
    {
        return $this->email_verified_at;
    }

    public function sendOtpNotification(string $code): void
    {
        $this->notify(new VerifyEmailWithOtp($code));
    }
}