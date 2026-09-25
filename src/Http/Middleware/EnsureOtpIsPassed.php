<?php

namespace Codewiser\Otp\Http\Middleware;

use Closure;
use Codewiser\Otp\Contracts\MustVerifyEmailWithOtp;
use Codewiser\Otp\Http\Controllers\EmailVerificationController;
use Codewiser\Otp\OtpVerify;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;

class EnsureOtpIsPassed extends EnsureEmailIsVerified
{
    public function __construct(public OtpVerify $otp)
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

        if (! $request->user() || (
                $request->user() instanceof MustVerifyEmailWithOtp &&
                $this->otp->needToVerifyEmail($request->user()) &&
                $this->otp->notPassed($request->session())
            )) {

            return redirect()
                ->guest(route($redirectToRoute ?: 'otp.email.show'))
                ->with([
                    'status' => $this->otp->sendNewCode(
                        $request->session(),
                        $request->user()
                    )
                ]);
        }

        return $next($request);
    }
}
