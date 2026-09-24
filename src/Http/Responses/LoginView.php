<?php

namespace Codewiser\Otp\Http\Responses;

use Codewiser\Otp\Contracts\LoginViewResponse;
use Codewiser\Otp\RateLimiter\OtpRateLimiter;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;

class LoginView implements LoginViewResponse
{
    public function __construct(protected $view)
    {
        //
    }

    public function toResponse($request): mixed
    {
        $availableIn = OtpRateLimiter::for(OtpRateLimiter::ISSUE, $request)->availableIn();

        if ($request->wantsJson()) {
            return new JsonResponse([
                'availableIn' => $availableIn
            ], 204);
        }

        if (! is_callable($this->view) || is_string($this->view)) {
            return view($this->view, [
                'request'     => $request,
                'availableIn' => $availableIn
            ]);
        }

        $response = call_user_func($this->view, $request, $availableIn);

        if ($response instanceof Responsable) {
            return $response->toResponse($request);
        }

        return $response;
    }
}