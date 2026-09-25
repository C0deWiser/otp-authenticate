<?php

namespace Codewiser\Otp\Http\Responses;

use Codewiser\Otp\Contracts\VerifyEmailViewResponse;
use Codewiser\Otp\Otp;
use Illuminate\Http\JsonResponse;

class EmailView extends LoginView implements VerifyEmailViewResponse
{
    public function toResponse($request): mixed
    {
        if (app(Otp::class)->passed($request->session())) {
            return $request->wantsJson()
                ? new JsonResponse('', 200)
                : redirect()->intended('/');
        }

        return parent::toResponse($request);
    }
}