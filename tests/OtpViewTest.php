<?php

namespace Codewiser\Otp\Tests;

use Codewiser\Otp\Contracts\LoginViewResponse;
use Codewiser\Otp\Contracts\VerifyEmailViewResponse;
use Codewiser\Otp\Otp;
use Codewiser\Otp\RateLimiter\OtpRateLimiter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class OtpViewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(Otp::class, new Otp);

        RateLimiter::for(OtpRateLimiter::ISSUE, fn (Request $request) => [
            \Illuminate\Cache\RateLimiting\Limit::perMinute(10)->by('test'),
        ]);
    }

    private function request(string $uri, bool $json = false): Request
    {
        $request = Request::create($uri, 'GET');

        $request->setLaravelSession(app('session')->driver());

        if ($json) {
            $request->headers->set('Accept', 'application/json');
        }

        return $request;
    }

    public function test_default_login_view_uses_otp_login_template()
    {
        Otp::loginView('otp::login');

        $response = app(LoginViewResponse::class)->toResponse($this->request('/otp/login'));

        $this->assertInstanceOf(View::class, $response);
        $this->assertSame('otp::login', $response->name());
    }

    public function test_login_view_can_be_overridden()
    {
        Otp::loginView('otp::verify-email');

        $response = app(LoginViewResponse::class)->toResponse($this->request('/otp/login'));

        $this->assertInstanceOf(View::class, $response);
        $this->assertSame('otp::verify-email', $response->name());
    }

    public function test_login_view_accepts_a_callable()
    {
        $arguments = [];

        Otp::loginView(function (Request $request, int $availableIn) use (&$arguments) {
            $arguments = func_get_args();

            return view('otp::login');
        });

        $response = app(LoginViewResponse::class)->toResponse($this->request('/otp/login'));

        $this->assertInstanceOf(Request::class, $arguments[0]);
        $this->assertSame(0, $arguments[1]);
        $this->assertInstanceOf(View::class, $response);
        $this->assertSame('otp::login', $response->name());
    }

    public function test_login_view_returns_available_in_for_json()
    {
        Otp::loginView('otp::login');

        $response = app(LoginViewResponse::class)->toResponse($this->request('/otp/login', json: true));

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame(['availableIn' => 0], json_decode($response->getContent(), true));
    }

    public function test_default_verify_email_view_uses_otp_verify_email_template()
    {
        Otp::verifyEmailView('otp::verify-email');

        $response = app(VerifyEmailViewResponse::class)->toResponse($this->request('/otp/email'));

        $this->assertInstanceOf(View::class, $response);
        $this->assertSame('otp::verify-email', $response->name());
    }

    public function test_verify_email_view_can_be_overridden()
    {
        Otp::verifyEmailView('otp::login');

        $response = app(VerifyEmailViewResponse::class)->toResponse($this->request('/otp/email'));

        $this->assertInstanceOf(View::class, $response);
        $this->assertSame('otp::login', $response->name());
    }

    public function test_verify_email_view_redirects_when_otp_was_passed()
    {
        Otp::verifyEmailView('otp::verify-email');

        $request = $this->request('/otp/email');
        $request->session()->put('otp_passed', true);

        $response = app(VerifyEmailViewResponse::class)->toResponse($request);

        $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $response);
    }
}