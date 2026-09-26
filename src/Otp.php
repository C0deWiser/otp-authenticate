<?php

namespace Codewiser\Otp;

use Codewiser\Otp\Contracts\MustVerifyEmailWithOtp;
use Codewiser\Otp\Contracts\VerifyEmailViewResponse;
use Codewiser\Otp\Contracts\LoginViewResponse;
use Codewiser\Otp\Http\Responses\EmailView;
use Codewiser\Otp\Http\Responses\LoginView;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;
use Psr\Log\LoggerAwareTrait;
use RuntimeException;

class Otp
{
    use LoggerAwareTrait;

    const string SENT = 'sent';
    const string LOST = 'lost';
    const string MISMATCH = 'mismatch';
    const string THROTTLE = 'throttled';
    const string USER = 'user';

    public function __construct(protected UserProvider $provider, protected StatefulGuard $guard)
    {
        //
    }

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
     *
     * @param  Authenticatable|string  $user
     */
    public function notPassed(Session $session, $user): bool
    {
        return ! $this->passed($session, $user);
    }

    /**
     * Check if otp authentication was passed?
     *
     * @param  Authenticatable|string  $user
     */
    public function passed(Session $session, $user): bool
    {
        $resolved = $this->resolve($user);

        if ($resolved && $session->has($this->key('otp_passed', $resolved))) {
            $this->logger?->debug("Otp passed");

            return true;
        } else {
            $this->logger?->debug("Otp not passed");

            return false;
        }
    }

    /**
     * Resolve the user, looking a guest email address up.
     *
     * @param  Authenticatable|string  $user
     */
    protected function resolve($user): ?Authenticatable
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
     * Send a new otp to the user.
     *
     * The login flow resolves a user by a guest email address, and should not
     * reveal whether that address is registered. Pass $strict to fail on a user
     * that cannot receive an otp, as the email verification flow does.
     *
     * @param  Authenticatable|string  $user
     *
     * @throws RuntimeException
     */
    public function sendNewCode(Session $session, $user, bool $strict = false): string
    {
        $resolved = $this->resolve($user);

        if (! $resolved instanceof MustVerifyEmailWithOtp) {
            if ($strict) {
                throw new RuntimeException('User must implement MustVerifyEmailWithOtp interface');
            }

            return self::SENT;
        }

        $resolved->sendOtpNotification($this->newCode($session, $user));

        $this->logger?->debug("Otp sent");

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

        $resolved = $this->resolve($user);

        if (! $resolved) {

            $this->logger?->warning("Otp verified, but user not found");

            throw ValidationException::withMessages([
                'email' => trans('otp::messages.'.self::USER)
            ]);
        }

        if ($resolved instanceof MustVerifyEmail) {
            $resolved->markEmailAsVerified();
        }

        $this->forgetCode($session, $user);

        $this->markAsPassed($session, $resolved);

        return $resolved;
    }

    /**
     * Log the user in, marking the otp as passed.
     */
    public function authenticate(Session $session, Authenticatable $user, bool $remember): void
    {
        $this->guard->login($user, $remember);

        if ($session->regenerate()) {
            $this->markAsPassed($session, $user);
        }
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

    protected function markAsPassed(Session $session, Authenticatable $user): void
    {
        $session->put($this->key('otp_passed', $user), true);

        $this->logger?->notice("Otp marked as passed");
    }
}