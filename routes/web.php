<?php

use Codewiser\Otp\Http\Controllers\AuthenticatedSessionController;
use Codewiser\Otp\Http\Controllers\EmailVerificationController;
use Codewiser\Otp\RateLimiter\OtpRateLimiter;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function () {

    Route::get('/email/otp', [EmailVerificationController::class, 'notice'])
        ->name('user-otp.notice');

    Route::post('/email/otp', [EmailVerificationController::class, 'issue'])
        ->middleware(ThrottleRequests::using(OtpRateLimiter::ISSUE))
        ->name('user-otp.send');

    Route::put('/email/otp', [EmailVerificationController::class, 'verify'])
        ->middleware(ThrottleRequests::using(OtpRateLimiter::VERIFY))
        ->name('user-otp.verify');
});

Route::middleware(['web', 'guest'])->group(function () {

    Route::get('/login/otp', [AuthenticatedSessionController::class, 'notice'])
        ->name('login-otp');

    Route::post('/login/otp', [AuthenticatedSessionController::class, 'issue'])
        ->middleware(ThrottleRequests::using(OtpRateLimiter::ISSUE))
        ->name('login-otp.send');

    Route::put('/login/otp', [AuthenticatedSessionController::class, 'verify'])
        ->middleware(ThrottleRequests::using(OtpRateLimiter::VERIFY))
        ->name('login-otp.verify');
});