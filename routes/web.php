<?php

use Codewiser\Otp\OtpController;
use Codewiser\Otp\Throttle;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {

    Route::get('/email/otp', [OtpController::class, 'notice'])
        ->name('user-otp.notice');

    Route::put('/email/otp', [OtpController::class, 'verify'])
        ->middleware('throttle:'.Throttle::verify->value)
        ->name('user-otp.verify');

    Route::post('/email/otp', [OtpController::class, 'issue'])
        ->middleware('throttle:'.Throttle::issue->value)
        ->name('user-otp.send');
});