<?php

namespace Codewiser\Otp\RateLimiter;

use Codewiser\Otp\OtpService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;

/**
 * Limit with custom response.
 */
class OtpLimit extends Limit
{
    public function for(Throttle $name): static
    {
        return $this->response(
            fn(Request $request, array $headers) => redirect()
                ->back(302, $headers)
                ->with('status', OtpService::OTP_THROTTLE)
                ->with('delay', OtpRateLimiter::for($name, $request)->forHumans())
        );
    }

}