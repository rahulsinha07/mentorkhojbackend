<?php

use App\Http\Controllers\Api\V1\DemoBookingController;
use Illuminate\Support\Facades\Route;

// Public create — no auth (free demo form from LPs)
Route::post('demo-bookings', [DemoBookingController::class, 'store']);

// Optional logged-in list
Route::middleware('auth:api')->group(function () {
    Route::get('demo-bookings/my', [DemoBookingController::class, 'my']);
});
