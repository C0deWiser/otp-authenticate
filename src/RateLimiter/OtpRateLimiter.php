<?php

namespace Codewiser\Otp\RateLimiter;

use Closure;
use Codewiser\Otp\OtpService;
use Exception;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Helper class, that handles Laravel RateLimiter.
 */
class OtpRateLimiter
{
    public static function for(Throttle $name, Request $request): static
    {
        return new static($name, $request);
    }

    public static function forIssuing(Request $request): static
    {
        return new static(Throttle::issue, $request);
    }

    public static function forVerifying(Request $request): static
    {
        return new static(Throttle::verify, $request);
    }

    public function __construct(public Throttle $name, public Request $request)
    {
        //
    }

    /**
     * Get RateLimiter limits.
     *
     * @return array{
     *     key: string,
     *     maxAttempts: integer,
     *     decaySeconds: integer,
     *     responseCallback: callable
     * }
     *
     * @see ThrottleRequests::handleRequestUsingNamedLimiter()
     */
    public function limits(): array
    {
        $limits = call_user_func(
            RateLimiter::limiter($this->name), $this->request
        );

        $limits = is_array($limits) ? $limits : [$limits];

        return array_map(
            fn(Limit $limit) => [
                'key'              => md5($this->name->value.$limit->key),
                'maxAttempts'      => $limit->maxAttempts,
                'decaySeconds'     => $limit->decaySeconds,
                'responseCallback' => $limit->responseCallback,
            ],
            $limits
        );
    }

    /**
     * Get number of seconds until next try.
     */
    public function availableIn(): int
    {
        $limits = $this->limits();

        if (! $limits) {
            return 0;
        }

        $availableIn = PHP_INT_MAX;

        foreach ($limits as $limit) {
            $availableIn = min($availableIn, RateLimiter::availableIn($limit['key']));
        }

        return $availableIn;
    }

    /**
     * Format availableIn to human-readable format.
     */
    public function forHumans(bool $short = false, int $parts = 1): string
    {
        $availableIn = $this->availableIn();

        $diff = now()->addSeconds($availableIn)->diffAsCarbonInterval(now());

        try {
            return $diff->forHumans(short: $short, parts: $parts);
        } catch (Exception) {
            return $availableIn;
        }
    }

    /**
     * Custom response for the rate limited request.
     */
    public function response(): Closure
    {
        return fn(Request $request, array $headers) => redirect()
            ->back(302, $headers)
            ->with('status', OtpService::OTP_THROTTLE)
            ->with('delay', $this->forHumans());
    }
}