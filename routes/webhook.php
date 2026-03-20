<?php

use App\Http\Controllers\Webhook\EmailController;
use App\Http\Controllers\Webhook\SmsController;
use App\Http\Middleware\VerifyMailgunSignature;
use App\Http\Middleware\VerifyTwilioSignature;
use Illuminate\Support\Facades\Route;

Route::post('/sms', SmsController::class)
    ->middleware(VerifyTwilioSignature::class);

Route::post('/email', EmailController::class)
    ->middleware(VerifyMailgunSignature::class);
