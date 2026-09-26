<?php

namespace Codewiser\Otp\Http\Controllers;

use Codewiser\Otp\Contracts\CodeVerifiedResponse;
use Codewiser\Otp\Contracts\CodeSentResponse;
use Codewiser\Otp\Contracts\ThrottledResponse;
use Codewiser\Otp\Contracts\VerifyEmailViewResponse;
use Codewiser\Otp\Otp;
use Codewiser\Otp\RateLimiter\OtpRateLimiter;
use Illuminate\Http\Request;

class EmailVerificationController
{
    public function __construct(public Otp $otp)
    {
        //
    }

    /**
     * Show notice view.
     */
    public function show(Request $request)
    {
        return app(VerifyEmailViewResponse::class);
    }

    /**
     * Send a new code.
     */
    public function store(Request $request)
    {
        $name = $request->has('send') ? OtpRateLimiter::ISSUE : OtpRateLimiter::VERIFY;

        $throttle = OtpRateLimiter::for($name, $request);

        $code = fn(Request $request) => $request->has('send')
            ? $this->issue($request)
            : $this->verify($request, $throttle);

        return $throttle->attempt($code)
            ?: app(ThrottledResponse::class, ['limiter' => $throttle]);
    }

    protected function issue(Request $request)
    {
        $status = $this->otp->sendNewCode(
            $request->session(),
            $request->user(),
            strict: true
        );

        return app(CodeSentResponse::class, [
            'status'     => $status,
            'redirectTo' => action([static::class, 'show'])
        ]);
    }

    protected function verify(Request $request, OtpRateLimiter $limiter)
    {
        $data = $request->validate(['code' => 'required|string']);

        $this->otp->validate(
            $request->session(),
            $request->user(),
            $data['code']
        );

        $limiter->clear();

        return app(CodeVerifiedResponse::class);
    }
}
