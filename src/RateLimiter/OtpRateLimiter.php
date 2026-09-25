<?php

namespace Codewiser\Otp\RateLimiter;

use Closure;
use Codewiser\Otp\Otp;
use Exception;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Helper class, that handles Laravel RateLimiter.
 */
class OtpRateLimiter
{
    const string ISSUE = 'otp-issue';
    const string VERIFY = 'otp-verify';

    public static function for(string $name, Request $request): static
    {
        return new static($name, $request);
    }

    public function __construct(public string $name, public Request $request)
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
        $limiter = RateLimiter::limiter($this->name);

        if (! $limiter) {
            return [];
        }

        $limits = call_user_func($limiter, $this->request);

        $limits = is_array($limits) ? $limits : [$limits];

        return array_map(
            fn(Limit $limit) => [
                'key'              => md5($this->name.$limit->key),
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
            return (string) $availableIn;
        }
    }

    /**
     * Custom response for the rate limited request.
     */
    public function response(): Closure
    {
        return fn(Request $request, array $headers) => $request->expectsJson()
            ? new JsonResponse(['message' => trans(Otp::OTP_THROTTLE)], 429, $headers)
            : redirect()
                ->back(302, $headers)
                ->with('status', trans(Otp::OTP_THROTTLE))
                ->with('delay', $this->forHumans());
    }
}