<?php

namespace Codewiser\Otp\Http\Middleware;

use Closure;
use Codewiser\Otp\Contracts\MustVerifyEmailWithOtp;
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
        if ($request->expectsJson()) {
            return parent::handle($request, $next, $redirectToRoute);
        }

        $user = $request->user();
        $session = $request->session();

        if ($user instanceof MustVerifyEmailWithOtp &&
            $user->shouldVerifyEmail() &&
            $this->otp->notPassed($session)
        ) {

            $redirect = redirect()->guest(route($redirectToRoute ?: 'otp.email.show'));

            if (OtpRateLimiter::for(OtpRateLimiter::ISSUE, $request)->availableIn() === 0) {
                $status = $this->otp->sendNewCode($session, $user);
                $redirect->with([
                    'status' => $status
                ]);
            }

            return $redirect;
        }

        return $next($request);
    }
}
