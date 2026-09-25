<?php

use Codewiser\Otp\Http\Controllers\AuthenticatedSessionController;
use Codewiser\Otp\Http\Controllers\EmailVerificationController;
use Codewiser\Otp\RateLimiter\OtpRateLimiter;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Route;

Route::middleware('web')
    ->prefix('otp')->as('otp.')
    ->group(function () {

        Route::middleware('auth')
            ->prefix('email')->as('email.')
            ->group(function () {
                Route::get('/', [EmailVerificationController::class, 'show'])->name('show');
                Route::post('/', [EmailVerificationController::class, 'issue'])->name('issue')
                    ->middleware(ThrottleRequests::using(OtpRateLimiter::ISSUE));
                Route::put('/', [EmailVerificationController::class, 'verify'])->name('verify')
                    ->middleware(ThrottleRequests::using(OtpRateLimiter::VERIFY));
            });

        Route::middleware('guest')
            ->prefix('login')->as('login.')
            ->group(function () {
                Route::get('/', [AuthenticatedSessionController::class, 'show'])->name('show');
                Route::post('/', [AuthenticatedSessionController::class, 'issue'])->name('issue')
                    ->middleware(ThrottleRequests::using(OtpRateLimiter::ISSUE));
                Route::put('/', [AuthenticatedSessionController::class, 'verify'])->name('verify')
                    ->middleware(ThrottleRequests::using(OtpRateLimiter::VERIFY));
            });
    });
