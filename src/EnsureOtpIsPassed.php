<?php

namespace Codewiser\Otp;

use Closure;
use Codewiser\Otp\Contracts\MustVerifyEmailWithOtp;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOtpIsPassed extends EnsureEmailIsVerified
{
    public function __construct(public OtpService $otp)
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

            return redirect()->guest(route($redirectToRoute ?: 'user-otp.notice'))->with([
                'status' => $this->otp->sendNewCode(
                    $request->session(),
                    $request->user()
                )
            ]);
        }

        return $next($request);
    }
}
