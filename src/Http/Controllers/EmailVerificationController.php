<?php

namespace Codewiser\Otp\Http\Controllers;

use Codewiser\Otp\Contracts\CodeVerifiedResponse;
use Codewiser\Otp\Contracts\SendRequestResponse;
use Codewiser\Otp\Contracts\VerifyEmailViewResponse;
use Codewiser\Otp\Http\Requests\VerifyRequest;
use Codewiser\Otp\OtpVerify;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationController
{
    public function __construct(public OtpVerify $otp)
    {
        //
    }

    /**
     * Show notice view.
     */
    public function notice(Request $request)
    {
        return app(VerifyEmailViewResponse::class);
    }

    /**
     * Send a new code.
     */
    public function issue(Request $request): JsonResponse|RedirectResponse
    {
        $status = $this->otp->sendNewCode(
            $request->session(),
            $request->user()
        );

        return app(SendRequestResponse::class, [
            'status'     => $status,
            'redirectTo' => 'user-otp.confirm'
        ]);
    }

    /**
     * Verify code.
     */
    public function verify(VerifyRequest $request): JsonResponse|RedirectResponse
    {
        $this->otp->validate(
            $request->session(),
            $request->user(),
            $request->input('code')
        );

        return app(CodeVerifiedResponse::class);
    }
}
