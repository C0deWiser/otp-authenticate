<?php

namespace Codewiser\Otp\Contracts;

use DateTimeInterface;
use Illuminate\Contracts\Auth\MustVerifyEmail;

interface MustVerifyEmailWithOtp extends MustVerifyEmail
{
    /**
     * Determine if the user should re-verify their email address.
     */
    public function shouldVerifyEmail(): bool;

    /**
     * Send the otp verification notification.
     */
    public function sendOtpNotification(string $code): void;
}