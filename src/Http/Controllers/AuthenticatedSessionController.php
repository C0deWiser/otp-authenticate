<?php

namespace Codewiser\Otp\Http\Controllers;

use Codewiser\Otp\Contracts\CodeVerifiedResponse;
use Codewiser\Otp\Contracts\LoginViewResponse;
use Codewiser\Otp\Contracts\SendRequestResponse;
use Codewiser\Otp\Http\Requests\LoginRequest;
use Codewiser\Otp\Http\Requests\SendRequest;
use Codewiser\Otp\OtpAuthenticate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;


class AuthenticatedSessionController
{
    public function __construct(public OtpAuthenticate $otp)
    {
        //
    }

    /**
     * Show otp login view.
     */
    public function notice(Request $request)
    {
        return app(LoginViewResponse::class);
    }

    /**
     * Send a new code.
     */
    public function issue(SendRequest $request): JsonResponse|RedirectResponse
    {
        $status = $this->otp->sendNewCode(
            $request->session(),
            Str::lower($request->{Fortify::email()})
        );

        return app(SendRequestResponse::class, [
            'status'     => $status,
            'redirectTo' => 'login-otp.confirm'
        ]);
    }

    /**
     * Verify code.
     */
    public function verify(LoginRequest $request): JsonResponse|RedirectResponse
    {
        $user = $this->otp->validate(
            $request->session(),
            Str::lower($request->{Fortify::email()}),
            $request->input('code')
        );

        if (! $request->user() && $user instanceof Authenticatable) {
            $this->otp->authenticate(
                $request->session(),
                $user,
                $request->boolean('remember')
            );
        }

        return app(CodeVerifiedResponse::class);
    }
}
