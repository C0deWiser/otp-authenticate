<?php

namespace Codewiser\Otp\Http\Middleware;

use Closure;
use Codewiser\Otp\Contracts\MustVerifyEmailWithOtp;
use Codewiser\Otp\Http\Controllers\EmailVerificationController;
use Codewiser\Otp\Otp;
use Codewiser\Otp\RateLimiter\OtpRateLimiter;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;

class EnsureOtpIsPassed extends EnsureEmailIsVerified
{
    public function __construct(public Otp $otp)
    {
        //
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $redirectToRoute
     *
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse|null
     */
    public function handle($request, Closure $next, $redirectToRoute = null)
    {
        $user = $request->user();

        if ($request->expectsJson() || ! $user instanceof MustVerifyEmailWithOtp) {
            return parent::handle($request, $next, $redirectToRoute);
        }

        $session = $request->session();

        if ($user->shouldVerifyEmail() && $this->otp->notPassed($session, $user)) {

            if ($redirectToRoute) {
                $redirect = redirect()->guest(route($redirectToRoute));
            } else {
                $redirect = redirect()->guest(action([EmailVerificationController::class, 'show']));
            }

            $limiter = OtpRateLimiter::for(OtpRateLimiter::ISSUE, $request);

            if ($limiter->tooManyAttempts()) {
                return $redirect;
            }

            $limiter->increment();

            return $redirect->with([
                'status' => $this->otp->sendNewCode($session, $user, strict: true)
            ]);
        }

        return $next($request);
    }
}
