<?php

namespace Codewiser\Otp\RateLimiter;

use Exception;
use InvalidArgumentException;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

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

    /**
     * Limits of the current request.
     *
     * They are resolved on creation, because a key may be derived from the
     * request, and a flow may authenticate the user in between counting and
     * clearing its attempts. A limiter built afterwards would count against
     * another key.
     *
     * @var array<int, array{
     *     key: string,
     *     maxAttempts: integer,
     *     decaySeconds: integer,
     *     responseCallback: null|callable
     * }>
     */
    protected array $limits;

    public function __construct(public string $name, public Request $request)
    {
        $this->limits = $this->resolveLimits();
    }

    /**
     * Get RateLimiter limits.
     *
     * @return array<int, array{
     *     key: string,
     *     maxAttempts: integer,
     *     decaySeconds: integer,
     *     responseCallback: null|callable
     * }>
     *
     * @see ThrottleRequests::handleRequestUsingNamedLimiter()
     */
    public function limits(): array
    {
        return $this->limits;
    }

    protected function resolveLimits(): array
    {
        $limiter = RateLimiter::limiter($this->name);

        if (! $limiter) {
            return [];
        }

        $limits = call_user_func($limiter, $this->request);

        $limits = is_array($limits) ? $limits : [$limits];

        return array_map(
            fn(Limit $limit) => [
                'key'              => md5($this->name.$this->key($limit)),
                'raw' => $this->key($limit),
                'maxAttempts'      => $limit->maxAttempts,
                'decaySeconds'     => $limit->decaySeconds,
                'responseCallback' => $limit->responseCallback,
            ],
            $limits
        );
    }

    /**
     * Get the key a limit is counted against.
     *
     * @throws InvalidArgumentException
     */
    protected function key(Limit $limit): string
    {
        if (! $limit->key) {
            throw new InvalidArgumentException(sprintf(
                'Rate limiter [%s] must scope each of its limits, use ->by() to define a key.',
                $this->name
            ));
        }

        return (string) $limit->key;
    }

    /**
     * Get the first exhausted limit.
     */
    protected function exhaustedLimit(): ?array
    {
        foreach ($this->limits() as $limit) {
            if (RateLimiter::tooManyAttempts($limit['key'], $limit['maxAttempts'])) {
                return $limit;
            }
        }

        return null;
    }

    /**
     * Get a response defined by the exhausted limit, if any.
     */
    public function customResponse(Request $request, array $headers = []): ?Response
    {
        $limit = $this->exhaustedLimit();

        if ($limit && is_callable($limit['responseCallback'])) {
            return call_user_func($limit['responseCallback'], $request, $headers);
        }

        return null;
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
     *
     * Limits are hit before the callback is executed, so that failed
     * attempts (a mismatch throws a ValidationException) are counted too.
     */
    public function attempt(callable $callback)
    {
        $limits = $this->limits();

        foreach ($limits as $limit) {
            if (RateLimiter::tooManyAttempts($limit['key'], $limit['maxAttempts'])) {
                return false;
            }
        }

        foreach ($limits as $limit) {
            RateLimiter::hit($limit['key'], $limit['decaySeconds']);
        }

        $result = call_user_func($callback, $this->request);

        if (is_null($result)) {
            $result = true;
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
}