<?php

namespace Codewiser\Otp\Contracts;

use DateTimeInterface;
use Illuminate\Contracts\Auth\MustVerifyEmail;

interface MustVerifyEmailWithOtp extends MustVerifyEmail
{
    /**
     * Get the datetime of email verification.
     */
    public function getEmailVerifiedAt(): ?DateTimeInterface;

    /**
     * Send the otp verification notification.
     */
    public function sendOtpNotification(string $code): void;
}