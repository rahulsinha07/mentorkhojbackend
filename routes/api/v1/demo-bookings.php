<?php

use App\Http\Controllers\Api\V1\DemoBookingController;
use App\Http\Controllers\Api\V1\SessionChatController;
use Illuminate\Support\Facades\Route;

// Public create — no auth (free demo form from LPs)
Route::post('demo-bookings', [DemoBookingController::class, 'store']);

// Optional logged-in list
Route::middleware('auth:api')->group(function () {
    Route::get('demo-bookings/my', [DemoBookingController::class, 'my']);
    Route::get('session-chats', [SessionChatController::class, 'index']);
    Route::post('session-chats', [SessionChatController::class, 'store']);
});
