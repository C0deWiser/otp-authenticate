<?php

namespace Codewiser\Otp\Traits;

use Codewiser\Otp\Notifications\EmailWithOtp;
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
        $this->notify(new EmailWithOtp($code));
    }
}