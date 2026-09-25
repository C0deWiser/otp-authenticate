<?php

namespace Codewiser\Otp\Http\Controllers;

use Codewiser\Otp\Contracts\CodeVerifiedResponse;
use Codewiser\Otp\Contracts\LoginViewResponse;
use Codewiser\Otp\Contracts\CodeSentResponse;
use Codewiser\Otp\Contracts\ThrottledResponse;
use Codewiser\Otp\OtpAuthenticate;
use Codewiser\Otp\RateLimiter\OtpRateLimiter;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

class AuthenticatedSessionController
{
    public function __construct(public OtpAuthenticate $otp)
    {
        //
    }

    /**
     * Show otp login view.
     */
    public function show(Request $request)
    {
        return app(LoginViewResponse::class);
    }

    /**
     * Send a new code.
     */
    public function store(Request $request)
    {
        $name = $request->has('send') ? OtpRateLimiter::ISSUE : OtpRateLimiter::VERIFY;
        $code = fn(Request $request) => $request->has('send') ? $this->issue($request) : $this->verify($request);

        $throttle = OtpRateLimiter::for($name, $request);

        return $throttle->attempt($code)
            ?: app(ThrottledResponse::class, ['limiter' => $throttle]);
    }

    protected function issue(Request $request)
    {
        $data = $request->validate([
            Fortify::email() => 'required|email'
        ]);

        $status = $this->otp->sendNewCode(
            $request->session(),
            Str::lower($data[Fortify::email()]),
        );

        return app(CodeSentResponse::class, [
            'status'     => $status,
            'redirectTo' => action([static::class, 'show'])
        ]);
    }

    protected function verify(Request $request)
    {
        $data = $request->validate([
            Fortify::email() => 'required|email',
            'code'           => 'required|string',
            'remember'       => 'sometimes',
        ]);

        $user = $this->otp->validate(
            $request->session(),
            Str::lower($data[Fortify::email()]),
            $data['code']
        );

        if (! $request->user() && $user instanceof Authenticatable) {
            $this->otp->authenticate(
                $request->session(),
                $user,
                (bool) ($data['remember'] ?? false)
            );
        }

        return app(CodeVerifiedResponse::class);
    }
}
