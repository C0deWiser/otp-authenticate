<?php

namespace Codewiser\Otp\Tests;

use Carbon\Carbon;
use InvalidArgumentException;
use Codewiser\Otp\RateLimiter\OtpRateLimiter;
use Codewiser\Otp\Tests\Fakes\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class OtpRateLimiterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::for(OtpRateLimiter::ISSUE, fn (Request $request) => [
            Limit::perMinute(2)->by('test-user'),
        ]);
    }

    private function limiter(): OtpRateLimiter
    {
        return OtpRateLimiter::for(OtpRateLimiter::ISSUE, Request::create('/otp/email', 'GET'));
    }

    public function test_limits_have_expected_structure()
    {
        $limits = $this->limiter()->limits();

        $this->assertCount(1, $limits);
        $this->assertSame(md5('otp-issue'.'test-user'), $limits[0]['key']);
        $this->assertSame(2, $limits[0]['maxAttempts']);
        $this->assertSame(60, $limits[0]['decaySeconds']);
    }

    public function test_available_in_is_zero_before_hit()
    {
        $this->assertSame(0, $this->limiter()->availableIn());
    }

    public function test_available_in_reflects_hit_time()
    {
        $key = md5('otp-issue'.'test-user');

        RateLimiter::hit($key, 60);
        RateLimiter::hit($key, 60);

        $this->assertGreaterThan(0, $this->limiter()->availableIn());
    }

    public function test_available_in_is_zero_when_limiter_is_not_registered()
    {
        $limiter = OtpRateLimiter::for(OtpRateLimiter::VERIFY, Request::create('/otp/email', 'GET'));

        $this->assertSame(0, $limiter->availableIn());
    }

    public function test_available_in_uses_depleted_limits()
    {
        Carbon::setTestNow(Carbon::now());

        try {
            RateLimiter::for(OtpRateLimiter::ISSUE, fn (Request $request) => [
                Limit::perMinute(2)->by('test-user-minute'),
                Limit::perDay(15)->by('test-user-day'),
            ]);

            $this->assertSame(0, $this->limiter()->availableIn());

            $minuteKey = md5(OtpRateLimiter::ISSUE.'test-user-minute');
            $dayKey = md5(OtpRateLimiter::ISSUE.'test-user-day');

            RateLimiter::hit($minuteKey);
            $this->assertSame(0, $this->limiter()->availableIn());

            RateLimiter::hit($minuteKey);
            $this->assertSame(60, $this->limiter()->availableIn());

            RateLimiter::increment($dayKey, 86400, 15);

            $this->assertSame(86400, $this->limiter()->availableIn());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_attempt_runs_callback_and_hits_limits()
    {
        $limiter = $this->limiter();

        $this->assertTrue($limiter->attempt(fn () => null));
        $this->assertSame(1, $limiter->attempts());
        $this->assertSame(2, $limiter->increment());
        $this->assertSame(2, $limiter->attempts());
        $this->assertTrue($limiter->tooManyAttempts());

        $called = false;
        $this->assertFalse($limiter->attempt(function () use (&$called) {
            $called = true;
        }));
        $this->assertFalse($called);
    }

    public function test_increment_and_decrement_apply_to_all_limits()
    {
        RateLimiter::for(OtpRateLimiter::ISSUE, fn (Request $request) => [
            Limit::perMinute(2)->by('minute'),
            Limit::perDay(3)->by('day'),
        ]);

        $limiter = $this->limiter();

        $this->assertSame(2, $limiter->increment(2));
        $this->assertSame(2, $limiter->attempts());
        $this->assertSame(0, $limiter->remaining());
        $this->assertSame(1, $limiter->decrement());
        $this->assertSame(1, $limiter->attempts());
    }

    public function test_attempts_and_retries_use_all_limits()
    {
        RateLimiter::for(OtpRateLimiter::ISSUE, fn (Request $request) => [
            Limit::perMinute(1)->by('minute'),
            Limit::perDay(3)->by('day'),
        ]);

        $limiter = $this->limiter();
        RateLimiter::hit(md5(OtpRateLimiter::ISSUE.'day'));

        $this->assertSame(1, $limiter->attempts());
        $this->assertSame(1, $limiter->remaining());
        $this->assertSame(1, $limiter->retriesLeft());
        $this->assertFalse($limiter->tooManyAttempts());

        $limiter->hit();

        $this->assertTrue($limiter->tooManyAttempts());
    }

    public function test_reset_attempts_and_clear_update_all_limits()
    {
        RateLimiter::for(OtpRateLimiter::ISSUE, fn (Request $request) => [
            Limit::perMinute(2)->by('minute'),
            Limit::perDay(3)->by('day'),
        ]);

        $limiter = $this->limiter();
        $limiter->hit();

        $this->assertTrue($limiter->resetAttempts());
        $this->assertSame(0, $limiter->attempts());
        $this->assertSame(0, $limiter->availableIn());
        $this->assertGreaterThan(0, RateLimiter::availableIn(md5(OtpRateLimiter::ISSUE.'minute')));

        $limiter->hit();
        $limiter->clear();

        $this->assertSame(0, $limiter->attempts());
        $this->assertSame(0, $limiter->availableIn());
    }

    public function test_for_humans_returns_interval_label()
    {
        $this->assertIsString($this->limiter()->forHumans());
        $this->assertNotSame('', $this->limiter()->forHumans());
    }

    public function test_for_humans_returns_readable_label_after_hit()
    {
        RateLimiter::hit(md5('otp-issue'.'test-user'), 3600);
        RateLimiter::hit(md5('otp-issue'.'test-user'), 3600);

        $this->assertMatchesRegularExpression('/[0-9]+ (minute|hour|second)/', $this->limiter()->forHumans());
    }

    public function test_limit_without_a_key_is_rejected()
    {
        RateLimiter::for(OtpRateLimiter::ISSUE, fn (Request $request) => [
            Limit::perMinute(1),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Rate limiter [otp-issue] must scope each of its limits');

        $this->limiter()->limits();
    }

    public function test_key_is_scoped_by_the_request_when_by_is_computed_from_it()
    {
        RateLimiter::for(OtpRateLimiter::VERIFY, fn (Request $request) => [
            Limit::perMinute(2)->by($request->user()?->getAuthIdentifier() ?: $request->ip()),
        ]);

        $request = Request::create('/otp/login', 'POST');

        $limiter = OtpRateLimiter::for(OtpRateLimiter::VERIFY, $request);
        $key = $limiter->limits()[0]['key'];

        $limiter->hit();

        // Logging in in the middle of a flow changes the key of a limiter built
        // from the same request, so a flow has to clear the very instance that
        // counted its attempts.
        $request->setUserResolver(fn () => new User(1));

        $this->assertNotSame($key, OtpRateLimiter::for(OtpRateLimiter::VERIFY, $request)->limits()[0]['key']);
        $this->assertSame($key, $limiter->limits()[0]['key']);
        $this->assertSame(1, $limiter->attempts());

        $limiter->clear();

        $this->assertSame(0, $limiter->attempts());
    }

    public function test_successful_attempts_are_counted_toward_the_limit()
    {
        $limiter = $this->limiter();

        for ($i = 0; $i < 2; $i++) {
            $this->assertTrue($limiter->attempt(fn () => null));
        }

        $this->assertSame(2, $limiter->attempts());
        $this->assertTrue($limiter->tooManyAttempts());
    }

    public function test_failed_attempts_deplete_the_limit()
    {
        $limiter = $this->limiter();

        $fail = function () {
            throw new \RuntimeException('code mismatch');
        };

        for ($i = 0; $i < 2; $i++) {
            try {
                $limiter->attempt($fail);
            } catch (\RuntimeException $e) {
                $this->assertSame('code mismatch', $e->getMessage());
            }
        }

        $this->assertSame(2, $limiter->attempts());
        $this->assertTrue($limiter->tooManyAttempts());
    }
}