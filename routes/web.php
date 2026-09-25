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
            ->prefix('email')
            ->group(function () {
                Route::get('/', [EmailVerificationController::class, 'show'])->name('email');
                Route::post('/', [EmailVerificationController::class, 'store'])->name('email.store');
            });

        Route::middleware('guest')
            ->prefix('login')
            ->group(function () {
                Route::get('/', [AuthenticatedSessionController::class, 'show'])->name('login');
                Route::post('/', [AuthenticatedSessionController::class, 'store'])->name('login.store');
            });
    });
