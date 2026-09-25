<?php

namespace Codewiser\Otp;

use Codewiser\Otp\Contracts\MustVerifyEmailWithOtp;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Session\Session;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

class OtpAuthenticate extends Otp
{
    public function __construct(protected UserProvider $provider, protected StatefulGuard $guard)
    {
        //
    }

    protected function resolveUser($user): ?Authenticatable
    {
        if (is_string($user) && $user !== '') {
            $user = $this->provider->retrieveByCredentials([
                Fortify::email() => $user,
            ]);
        }

        if ($user instanceof Authenticatable) {
            return $user;
        }

        return null;
    }

    /**
     * Send a new otp to the user, if resolves them.
     *
     * @param  string  $user  Guest email address.
     */
    public function sendNewCode(Session $session, $user): string
    {
        $resolved = $this->resolveUser($user);

        if ($resolved instanceof MustVerifyEmailWithOtp) {
            $resolved->sendOtpNotification($this->newCode($session, $user));
            $this->logger?->debug("Otp sent");
        }

        // Prevent enumerating emails
        return self::SENT;
    }

    /**
     * Compare code against generated, if user was resolved.
     *
     * @param  string  $user  Guest email address.
     *
     * @throws ValidationException
     */
    public function validate(Session $session, $user, ?string $code): ?Authenticatable
    {
        parent::validate($session, $user, $code);

        $user = $this->resolveUser($user);

        if (! $user) {
            $this->logger?->warning("Otp verified, but user not found");

            throw ValidationException::withMessages([
                'email' => trans('otp::messages.'.self::USER)
            ]);
        }

        if ($user instanceof MustVerifyEmail) {
            $user->markEmailAsVerified();
            return $user;
        }

        return null;
    }

    public function authenticate(Session $session, Authenticatable $user, bool $remember): void
    {
        $this->guard->login($user, $remember);

        if ($session->regenerate()) {
            $this->markAsPassed($session, $user);
        }
    }
}