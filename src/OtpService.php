<?php

namespace Codewiser\Otp;

use Closure;
use Codewiser\Otp\Contracts\MustVerifyEmailWithOtp;
use DateInterval;
use Exception;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Psr\Log\LoggerAwareTrait;

class OtpService
{
    use LoggerAwareTrait;

    const OTP_SENT = 'otp-sent';
    const OTP_LOST = 'otp-lost';
    const OTP_MISMATCH = 'otp-mismatch';
    const OTP_THROTTLE = 'otp-throttle';

    /**
     * @var null|Closure(): string
     */
    public static ?Closure $newCodeCallback = null;

    /**
     * @var null|callable|string
     */
    public static $view;

    /**
     * Set a callback that should be used for otp generation.
     *
     * @param  callable():string  $callback
     */
    public static function newCodeUsing(callable $callback): void
    {
        static::$newCodeCallback = $callback;
    }

    /**
     * Set a view for user-form.
     */
    public static function view(callable|string $view): void
    {
        self::$view = $view;
    }

    /**
     * @param  null|string  $emailVerificationCooldown  re-verify email: null for every session; DateInterval after period.
     */
    public function __construct(protected ?string $emailVerificationCooldown = null)
    {
        //
    }

    public function noticeView(Request $request, int $availableIn)
    {
        if (is_callable(self::$view)) {
            return call_user_func(self::$view, $request, $availableIn);
        }

        $data = ['request' => $request, 'availableIn' => $availableIn];

        if (is_string(self::$view)) {
            return view(self::$view, $data);
        }

        return view('auth.otp-email', $data);
    }

    /**
     * Check if user email needs to be re-verified?
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

            if (! $this->emailVerificationCooldown) {

                $this->logger?->debug("Cooldown disabled, enabling otp");

                return true;
            }

            $verifiedAt = Carbon::make($verifiedAt);
            $this->logger?->debug("Email verified at $verifiedAt");

            $validUntil = $verifiedAt->add(new DateInterval($this->emailVerificationCooldown));
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

    protected function markAsPassed(Session $session): void
    {
        $this->forgetCode($session);

        $session->put('otp_passed', true);

        $this->logger?->notice("Otp marked as passed");
    }

    /**
     * Compare code against generated.
     */
    public function validate(Session $session, $user, ?string $code): void
    {
        $storedOtp = $this->getCode($session);

        if (! $storedOtp) {

            $this->logger?->error("Otp lost");

            $this->sendNewCode($session, $user);

            throw ValidationException::withMessages([
                'otp' => __(self::OTP_LOST)
            ]);
        }

        if ($code != $storedOtp) {

            $this->logger?->warning("Otp mismatch $code != $storedOtp");

            throw ValidationException::withMessages([
                'otp' => __(self::OTP_MISMATCH)
            ]);
        }

        $this->markAsPassed($session);

        if ($user instanceof MustVerifyEmail) {
            $user->markEmailAsVerified();
        }
    }

    protected function forgetCode(Session $session): void
    {
        $session->forget('otp');
    }

    protected function getCode(Session $session): ?string
    {
        return $session->get('otp');
    }

    protected function newCode(Session $session): string
    {
        if (is_callable(self::$newCodeCallback)) {
            $otp = call_user_func(self::$newCodeCallback);
        } else {
            $otp = Str::password(6, letters: false, symbols: false);
        }

        $session->put('otp', $otp);

        return $otp;
    }

    /**
     * Send a new otp to the user.
     */
    public function sendNewCode(Session $session, $user): ?string
    {
        if ($user instanceof MustVerifyEmailWithOtp) {

            $otp = $this->newCode($session);
            $user->sendOtpNotification($otp);

            $this->logger?->debug("Otp sent: $otp");

            return self::OTP_SENT;
        }

        return null;
    }
}