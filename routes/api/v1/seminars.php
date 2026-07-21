<?php

use App\Http\Controllers\Api\V1\PaymentGatewayController;
use App\Http\Controllers\Api\V1\Seminar\SeminarBookingController;
use App\Http\Controllers\Api\V1\Seminar\SeminarPublicController;
use Illuminate\Support\Facades\Route;

Route::get('payment-gateway/razor-pay/public-key', [PaymentGatewayController::class, 'razorPayPublicKey']);

Route::group(['prefix' => 'seminars'], function () {
    Route::get('/', [SeminarPublicController::class, 'index']);
    Route::get('{slug}', [SeminarPublicController::class, 'show']);

    Route::middleware('auth:api')->group(function () {
        Route::post('{slug}/register', [SeminarPublicController::class, 'register']);
        Route::post('{slug}/book', [SeminarBookingController::class, 'book']);
    });
});

Route::middleware('auth:api')->group(function () {
    Route::post('seminar-bookings/{id}/payment-order', [SeminarBookingController::class, 'createPaymentOrder']);
    Route::post('seminar-bookings/{id}/verify-payment', [SeminarBookingController::class, 'verifyPayment']);
    Route::post('seminar-bookings/{id}/payment-failed', [SeminarBookingController::class, 'reportPaymentFailure']);
    Route::get('seminar-bookings/my', [SeminarBookingController::class, 'myBookings']);
});
