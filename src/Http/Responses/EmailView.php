<?php

namespace Codewiser\Otp\Http\Responses;

use Codewiser\Otp\Contracts\VerifyEmailViewResponse;
use Codewiser\Otp\OtpVerify;

class EmailView extends LoginView implements VerifyEmailViewResponse
{
    public function toResponse($request): mixed
    {
        if (! $request->wantsJson()) {
            if (app(OtpVerify::class)->passed($request->session())) {
                return redirect()->intended('/');
            }
        }

        return parent::toResponse($request);
    }
}