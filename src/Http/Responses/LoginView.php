<?php

namespace Codewiser\Otp\Http\Responses;

use Codewiser\Otp\Contracts\LoginViewResponse;
use Codewiser\Otp\RateLimiter\OtpRateLimiter;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Psr\Log\LoggerAwareTrait;

class LoginView implements LoginViewResponse
{
    use LoggerAwareTrait;

    public function __construct(protected $view)
    {
        //
    }

    public function toResponse($request): mixed
    {
        // We need an email to build limiter key
        if ($old_input = $request->session()->get('_old_input')) {
            $limiter = OtpRateLimiter::for(OtpRateLimiter::ISSUE, new Request($old_input));
        } else {
            $limiter = OtpRateLimiter::for(OtpRateLimiter::ISSUE, $request);
        }

        $retryAfter = $limiter->availableIn();

        $this->logger?->debug(class_basename(__CLASS__), [
            'request'    => $request->method().' '.$request->path(),
            'retryAfter' => $retryAfter,
            'input'      => $request->input(),
        ]);

        if ($request->wantsJson()) {
            return new JsonResponse('', 204, ['Retry-After' => $retryAfter]);
        }

        if (! is_callable($this->view) || is_string($this->view)) {
            return view($this->view, [
                'request'     => $request,
                'availableIn' => $retryAfter
            ]);
        }

        $response = call_user_func($this->view, $request, $retryAfter);

        if ($response instanceof Responsable) {
            return $response->toResponse($request);
        }

        return $response;
    }
}