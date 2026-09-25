<?php

namespace Codewiser\Otp\Tests;

use Carbon\Carbon;
use Codewiser\Otp\RateLimiter\OtpRateLimiter;
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
}