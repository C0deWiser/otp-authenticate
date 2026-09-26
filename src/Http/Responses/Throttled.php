<?php

namespace Codewiser\Otp\Http\Responses;

use Codewiser\Otp\Contracts\ThrottledResponse;
use Codewiser\Otp\Otp;
use Codewiser\Otp\RateLimiter\OtpRateLimiter;
use Illuminate\Http\JsonResponse;
use Psr\Log\LoggerAwareTrait;

class Throttled implements ThrottledResponse
{
    use LoggerAwareTrait;

    public function __construct(public OtpRateLimiter $limiter)
    {
        //
    }

    public function toResponse($request)
    {
        if ($response = $this->limiter->customResponse($request)) {
            return $response;
        }

        $retryAfter = $this->limiter->availableIn();

        $this->logger?->debug(class_basename(__CLASS__), [
            'request'    => $request->method().' '.$request->path(),
            'input'      => $request->input(),
            'retryAfter' => $retryAfter,
        ]);

        $message = trans('otp::messages.'.Otp::THROTTLE, ['seconds' => $retryAfter]);

        return $request->expectsJson()
            ? new JsonResponse(['message' => $message], 429, ['Retry-After' => $retryAfter])
            : redirect()->back()->withErrors([
                'code' => $message
            ])->withInput($request->input());
    }
}