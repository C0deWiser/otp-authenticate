<?php

use Codewiser\Otp\Controllers\OtpController;
use Codewiser\Otp\RateLimiter\Throttle;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {

    Route::get('/email/otp', [OtpController::class, 'notice'])
        ->name('user-otp.notice');

    Route::put('/email/otp', [OtpController::class, 'verify'])
        ->middleware(ThrottleRequests::using(Throttle::verify))
        ->name('user-otp.verify');

    Route::post('/email/otp', [OtpController::class, 'issue'])
        ->middleware(ThrottleRequests::using(Throttle::issue))
        ->name('user-otp.send');
});