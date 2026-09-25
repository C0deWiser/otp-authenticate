<?php

namespace Codewiser\Otp\RateLimiter;

use Closure;
use Codewiser\Otp\Otp;
use Exception;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Helper class, that handles Laravel RateLimiter.
 */
class OtpRateLimiter implements Responsable
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
     * Increment (by 1) the counter.
     */
    public function hit(): void
    {
        foreach ($this->limits() as $limit) {
            RateLimiter::hit($limit['key'], $limit['decaySeconds']);
        }
    }

    /**
     * Attempts to execute a callback if it's not limited.
     */
    public function attempt(callable $callback)
    {
        $limits = $this->limits();

        foreach ($limits as $limit) {
            if (RateLimiter::tooManyAttempts($limit['key'], $limit['maxAttempts'])) {
                return false;
            }
        }

        $result = call_user_func($callback, $this->request);

        if (is_null($result)) {
            $result = true;
        }

        foreach ($limits as $limit) {
            RateLimiter::hit($limit['key'], $limit['decaySeconds']);
        }

        return $result;
    }

    /**
     * Determine if there are too many times.
     */
    public function tooManyAttempts(): bool
    {
        foreach ($this->limits() as $limit) {
            if (RateLimiter::tooManyAttempts($limit['key'], $limit['maxAttempts'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Increment the counter by a given amount.
     */
    public function increment(int $amount = 1): int
    {
        $attempts = [];

        foreach ($this->limits() as $limit) {
            $attempts[] = RateLimiter::increment(
                $limit['key'],
                $limit['decaySeconds'],
                $amount
            );
        }

        return $attempts ? max($attempts) : 0;
    }

    /**
     * Decrement the counter by a given amount.
     */
    public function decrement(int $amount = 1): int
    {
        $attempts = [];

        foreach ($this->limits() as $limit) {
            $attempts[] = RateLimiter::decrement(
                $limit['key'],
                $limit['decaySeconds'],
                $amount
            );
        }

        return $attempts ? max($attempts) : 0;
    }

    /**
     * Get the number of attempts.
     */
    public function attempts()
    {
        $attempts = [];

        foreach ($this->limits() as $limit) {
            $attempts[] = (int) RateLimiter::attempts($limit['key']);
        }

        return $attempts ? max($attempts) : 0;
    }

    /**
     * Reset the number of attempts.
     */
    public function resetAttempts(): bool
    {
        $limits = $this->limits();

        if (! $limits) {
            return false;
        }

        $reset = true;

        foreach ($limits as $limit) {
            if (! RateLimiter::resetAttempts($limit['key'])) {
                $reset = false;
            }
        }

        return $reset;
    }

    /**
     * Get the number of retries left.
     */
    public function remaining(): int
    {
        $remaining = [];

        foreach ($this->limits() as $limit) {
            $remaining[] = RateLimiter::remaining(
                $limit['key'],
                $limit['maxAttempts']
            );
        }

        return $remaining ? min($remaining) : 0;
    }

    /**
     * Get the number of retries left.
     */
    public function retriesLeft(): int
    {
        return $this->remaining();
    }

    /**
     * Clear the hits and lockout timer.
     */
    public function clear(): void
    {
        foreach ($this->limits() as $limit) {
            RateLimiter::clear($limit['key']);
        }
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

        $availableIn = 0;

        foreach ($limits as $limit) {
            if (RateLimiter::attempts($limit['key']) < $limit['maxAttempts']) {
                continue;
            }

            $availableIn = max($availableIn, RateLimiter::availableIn($limit['key']));
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
     * Returns Throttled response.
     */
    public function toResponse($request): Response
    {
        return ($this->response())($request, []);
    }

    /**
     * Custom response for the rate limited request.
     */
    public function response(): Closure
    {
        return function (Request $request, array $headers) {

            $message = trans('otp::messages.'.Otp::THROTTLE, [
                'seconds' => $this->availableIn()
            ]);

            return $request->expectsJson()
                ? new JsonResponse(['message' => $message], 429, $headers)
                : redirect()
                    ->back(302, $headers)
                    ->withErrors([
                        'code' => $message
                    ]);
        };
    }
}