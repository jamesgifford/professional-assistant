<?php

use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\HealthController;
use Illuminate\Support\Facades\Route;

Route::post('/chat', [ChatController::class, 'store']);
Route::get('/chat/test', [ChatController::class, 'test']);
Route::get('/health', HealthController::class);
