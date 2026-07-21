<?php

use App\Http\Controllers\Api\V1\Mentor\MentorAdminController;
use App\Http\Controllers\Api\V1\Mentor\MentorBookingController;
use App\Http\Controllers\Api\V1\Mentor\MentorDashboardController;
use App\Http\Controllers\Api\V1\Mentor\MentorEarningsController;
use App\Http\Controllers\Api\V1\Mentor\MentorFavoriteController;
use App\Http\Controllers\Api\V1\Mentor\MentorPublicController;
use App\Http\Controllers\Api\V1\Mentor\MentorServiceController;
use Illuminate\Support\Facades\Route;

Route::group(['namespace' => 'Api\V1\Mentor'], function () {

    // Public mentor routes
    Route::group(['prefix' => 'mentors'], function () {
        Route::get('/', [MentorPublicController::class, 'index']);
        Route::get('check-username/{username}', [MentorPublicController::class, 'checkUsername']);
        Route::get('share-templates', [MentorPublicController::class, 'shareTemplates']);
        Route::get('{usernameOrId}/photo', [MentorPublicController::class, 'photo']);
        Route::get('{usernameOrId}/services', [MentorPublicController::class, 'services']);
        Route::get('{usernameOrId}', [MentorPublicController::class, 'show']);

        Route::group(['middleware' => ['auth:api', 'customer_is_block']], function () {
            Route::post('{id}/book', [MentorBookingController::class, 'book']);
            Route::get('my/bookings/{id}', [MentorBookingController::class, 'showMyBooking']);
            Route::get('my/bookings/{id}/checkout-context', [MentorBookingController::class, 'checkoutContext']);
            Route::post('my/bookings/{id}/verify-payment', [MentorBookingController::class, 'verifyPayment']);
            Route::post('my/bookings/{id}/payment-failed', [MentorBookingController::class, 'reportPaymentFailure']);
            Route::get('my/bookings', [MentorBookingController::class, 'myBookings']);
            Route::get('my/favorites', [MentorFavoriteController::class, 'index']);
            Route::post('{id}/favorite', [MentorFavoriteController::class, 'store']);
            Route::delete('{id}/favorite', [MentorFavoriteController::class, 'destroy']);
        });
    });

    // Authenticated mentor dashboard
    Route::group(['prefix' => 'mentor/me', 'middleware' => ['auth:api', 'customer_is_block']], function () {
        Route::get('/', [MentorDashboardController::class, 'show']);
        Route::post('/', [MentorDashboardController::class, 'store']);
        Route::put('/', [MentorDashboardController::class, 'update']);
        Route::patch('publish', [MentorDashboardController::class, 'publish']);
        Route::put('settings', [MentorDashboardController::class, 'updateSettings']);

        Route::get('services', [MentorServiceController::class, 'index']);
        Route::post('services', [MentorServiceController::class, 'store']);
        Route::put('services/{id}', [MentorServiceController::class, 'update']);
        Route::patch('services/{id}/enabled', [MentorServiceController::class, 'toggleEnabled']);
        Route::delete('services/{id}', [MentorServiceController::class, 'destroy']);

        Route::get('bookings', [MentorBookingController::class, 'mentorBookings']);
        Route::patch('bookings/{id}/status', [MentorBookingController::class, 'updateStatus']);

        Route::get('earnings', [MentorEarningsController::class, 'summary']);
        Route::get('earnings/transactions', [MentorEarningsController::class, 'transactions']);
        Route::post('payouts', [MentorEarningsController::class, 'requestPayout']);
        Route::get('payouts', [MentorEarningsController::class, 'payouts']);

        Route::get('share', [MentorDashboardController::class, 'shareHub']);
        Route::get('share/templates', [MentorDashboardController::class, 'shareTemplates']);
        Route::get('share/compose/{templateSlug}', [MentorDashboardController::class, 'composeShare']);
        Route::post('share/log', [MentorDashboardController::class, 'logShare']);
        Route::put('share/caption', [MentorDashboardController::class, 'updateShareCaption']);
    });
});
