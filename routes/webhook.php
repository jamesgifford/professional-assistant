<?php

use App\Http\Controllers\Webhook\EmailController;
use App\Http\Controllers\Webhook\SmsController;
use App\Http\Middleware\VerifyResendSignature;
use App\Http\Middleware\VerifyTwilioSignature;
use Illuminate\Support\Facades\Route;

Route::post('/sms', SmsController::class)
    ->middleware(VerifyTwilioSignature::class);

Route::post('/resend/inbound', EmailController::class)
    ->middleware(VerifyResendSignature::class);
