<?php

namespace Codewiser\Otp;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class OtpController
{
    public function __construct(public OtpService $otp)
    {
        //
    }

    /**
     * Show notice view.
     */
    public function notice(Request $request)
    {
        if ($request->wantsJson()) {
            return response()->json('', 204);
        }

        if ($this->otp->passed($request->session())) {
            return redirect()->intended('/');
        }

        return $this->otp->noticeView(
            $request,
            OtpRateLimiter::forIssuing($request)->availableIn()
        );
    }

    /**
     * Send a new code.
     */
    public function issue(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return response()->json('', 204);
        }

        return redirect()->back()->with([
            'status' => $this->otp->sendNewCode(
                $request->session(),
                $request->user()
            )
        ]);
    }

    /**
     * Verify OTP code.
     */
    public function verify(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return response()->json('', 204);
        }

        $this->otp->validate(
            $request->session(),
            $request->user(),
            $request->input('otp')
        );

        return redirect()->intended('/');
    }
}
