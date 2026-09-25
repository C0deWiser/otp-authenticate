<?php

namespace Codewiser\Otp;

use Codewiser\Otp\Contracts\MustVerifyEmailWithOtp;
use Codewiser\Otp\Contracts\VerifyEmailViewResponse;
use Codewiser\Otp\Contracts\LoginViewResponse;
use Codewiser\Otp\Http\Responses\EmailView;
use Codewiser\Otp\Http\Responses\LoginView;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Psr\Log\LoggerAwareTrait;

class Otp
{
    use LoggerAwareTrait;

    const string SENT = 'sent';
    const string LOST = 'lost';
    const string MISMATCH = 'mismatch';
    const string THROTTLE = 'throttled';
    const string USER = 'user';

    /**
     * Specify which view should be used as the login view.
     */
    public static function loginView(callable|string $view): void
    {
        app()->singleton(LoginViewResponse::class, fn() => new LoginView($view));
    }

    /**
     * Specify which view should be used as the email verification prompt.
     */
    public static function verifyEmailView(callable|string $view): void
    {
        app()->singleton(VerifyEmailViewResponse::class, fn() => new EmailView($view));
    }

    /**
     * @var null|callable(): string
     */
    protected static $newCodeCallback = null;

    /**
     * Set a callback that should be used for otp generation.
     *
     * @param  callable(): string  $callback
     */
    public static function newCodeUsing(callable $callback): void
    {
        static::$newCodeCallback = $callback;
    }

    /**
     * Check if otp authentication was not passed?
     */
    public function notPassed(Session $session): bool
    {
        return ! $this->passed($session);
    }

    /**
     * Check if otp authentication was passed?
     */
    public function passed(Session $session): bool
    {
        if ($session->has('otp_passed')) {
            $this->logger?->debug("Otp passed");

            return true;
        } else {
            $this->logger?->debug("Otp not passed");

            return false;
        }
    }

    /**
     * Send a new otp to the user.
     *
     * @param  Authenticatable|string  $user
     */
    public function sendNewCode(Session $session, $user): string
    {
        if ($user instanceof MustVerifyEmailWithOtp) {
            $user->sendOtpNotification($this->newCode($session, $user));
            $this->logger?->debug("Otp sent");
        } else {
            throw new \RuntimeException('User must implement MustVerifyEmailWithOtp interface');
        }

        return self::SENT;
    }

    /**
     * Compare code against generated.
     *
     * @param  Authenticatable|string  $user
     *
     * @return Authenticatable|null  The resolved authenticated user.
     * @throws ValidationException
     */
    public function validate(Session $session, $user, ?string $code): ?Authenticatable
    {
        $storedOtp = $this->getCode($session, $user);

        if (! $storedOtp) {

            $this->logger?->error("Otp lost");

            $this->sendNewCode($session, $user);

            throw ValidationException::withMessages([
                'code' => trans('otp::messages.'.self::LOST)
            ]);
        }

        if ($code !== $storedOtp) {

            $this->logger?->warning("Otp mismatch");

            throw ValidationException::withMessages([
                'code' => trans('otp::messages.'.self::MISMATCH)
            ]);
        }

        $this->markAsPassed($session, $user);

        if ($user instanceof MustVerifyEmail) {
            $user->markEmailAsVerified();
            return $user;
        }

        return null;
    }

    protected function forgetCode(Session $session, Authenticatable|string $user): void
    {
        $session->forget($this->key('otp', $user));
    }

    protected function getCode(Session $session, Authenticatable|string $user): ?string
    {
        return $session->get($this->key('otp', $user));
    }

    protected function newCode(Session $session, Authenticatable|string $user): string
    {
        if (is_callable(self::$newCodeCallback)) {
            $otp = call_user_func(self::$newCodeCallback);
        } else {
            $otp = Str::password(6, letters: false, symbols: false);
        }

        $session->put($this->key('otp', $user), $otp);

        return $otp;
    }

    protected function key(string $prefix, Authenticatable|string $user): string
    {
        if ($user instanceof Authenticatable) {
            return $prefix.':'.$user->getAuthIdentifierName().':'.$user->getAuthIdentifier();
        }

        return $prefix.':'.$user;
    }

    protected function markAsPassed(Session $session, Authenticatable|string $user): void
    {
        $this->forgetCode($session, $user);

        $session->put('otp_passed', true);

        $this->logger?->notice("Otp marked as passed");
    }
}