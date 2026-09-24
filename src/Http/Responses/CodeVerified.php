<?php

namespace Codewiser\Otp\Http\Responses;

use Codewiser\Otp\Contracts\CodeVerifiedResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CodeVerified implements CodeVerifiedResponse
{
    public function toResponse($request): Response
    {
        return $request->wantsJson()
            ? new JsonResponse('', 200)
            : redirect()->intended('/');
    }
}