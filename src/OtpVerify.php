<?php

namespace Codewiser\Otp;

use Codewiser\Otp\Contracts\MustVerifyEmailWithOtp;
use DateInterval;
use Exception;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;

class OtpVerify extends Otp
{
    /**
     * @param  null|string  $cooldown  re-verify email: null for every session; DateInterval after period.
     */
    public function __construct(public ?string $cooldown)
    {
        //
    }

    /**
     * Check if the user email needs to be re-verified.
     *
     * @throws Exception when the duration cannot be parsed as an interval.
     */
    public function needToVerifyEmail($user): bool
    {
        if ($user instanceof MustVerifyEmailWithOtp) {

            $verifiedAt = $user->getEmailVerifiedAt();

            if (! $verifiedAt) {

                $this->logger?->debug("Email not verified, enabling otp");

                return true;
            }

            if (! $this->cooldown) {

                $this->logger?->debug("Cooldown disabled, enabling otp");

                return true;
            }

            $verifiedAt = Carbon::make($verifiedAt);
            $this->logger?->debug("Email verified at $verifiedAt");

            $validUntil = $verifiedAt->add(new DateInterval($this->cooldown));
            $this->logger?->debug("Email valid until $validUntil");

            if ($validUntil->isPast()) {
                $this->logger?->debug("Email verification outdated, enabling otp");
                return true;
            } else {
                $this->logger?->debug("Email verification is valid, disabling otp");
            }
        }

        return false;
    }

    public function resolveUser($user): ?Authenticatable
    {
        if ($user instanceof Authenticatable) {
            return $user;
        }

        return null;
    }
}