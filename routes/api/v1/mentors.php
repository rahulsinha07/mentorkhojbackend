<?php

use App\Http\Controllers\Api\V1\Mentor\MentorAdminController;
use App\Http\Controllers\Api\V1\Mentor\MentorBookingController;
use App\Http\Controllers\Api\V1\Mentor\MentorDashboardController;
use App\Http\Controllers\Api\V1\Mentor\MentorDocumentController;
use App\Http\Controllers\Api\V1\Mentor\MentorDocumentManagementController;
use App\Http\Controllers\Api\V1\Mentor\MentorEarningsController;
use App\Http\Controllers\Api\V1\Mentor\MentorFavoriteController;
use App\Http\Controllers\Api\V1\Mentor\MentorPublicController;
use App\Http\Controllers\Api\V1\Mentor\MentorServiceController;
use App\Http\Controllers\Api\V1\Mentor\PrepBoardController;
use Illuminate\Support\Facades\Route;

Route::group(['namespace' => 'Api\V1\Mentor'], function () {

    // Public mentor routes
    Route::group(['prefix' => 'mentors'], function () {
        Route::get('/', [MentorPublicController::class, 'index']);
        Route::get('check-username/{username}', [MentorPublicController::class, 'checkUsername']);
        Route::get('share-templates', [MentorPublicController::class, 'shareTemplates']);
        Route::get('{usernameOrId}/photo', [MentorPublicController::class, 'photo']);
        Route::get('{usernameOrId}/services', [MentorPublicController::class, 'services']);
        Route::get('{usernameOrId}/documents', [MentorPublicController::class, 'documents']);
        Route::get('{usernameOrId}', [MentorPublicController::class, 'show']);

        Route::group(['middleware' => ['auth:api', 'customer_is_block']], function () {
            Route::post('{id}/book', [MentorBookingController::class, 'book']);
            Route::post('{id}/documents/{documentId}/purchase', [MentorDocumentController::class, 'purchase']);
            Route::get('my/document-purchases/{id}/checkout-context', [MentorDocumentController::class, 'checkoutContext']);
            Route::post('my/document-purchases/{id}/verify-payment', [MentorDocumentController::class, 'verifyPayment']);
            Route::get('my/document-purchases', [MentorDocumentController::class, 'myPurchases']);
            Route::get('my/document-purchases/{id}/download/{fileId}', [MentorDocumentController::class, 'download']);
            Route::get('my/bookings/{id}/history', [MentorBookingController::class, 'bookingHistory']);
            Route::get('my/bookings/{id}/ics', [MentorBookingController::class, 'downloadMyIcs']);
            Route::post('my/bookings/{id}/cancel', [MentorBookingController::class, 'cancelMyBooking']);
            Route::get('my/bookings/{id}', [MentorBookingController::class, 'showMyBooking']);
            Route::get('my/bookings/{id}/checkout-context', [MentorBookingController::class, 'checkoutContext']);
            Route::post('my/bookings/{id}/verify-payment', [MentorBookingController::class, 'verifyPayment']);
            Route::post('my/bookings/{id}/payment-failed', [MentorBookingController::class, 'reportPaymentFailure']);
            Route::patch('my/bookings/{id}/schedule', [MentorBookingController::class, 'updateMySchedule']);
            Route::post('my/bookings/{id}/schedule', [MentorBookingController::class, 'scheduleMyBooking']);
            Route::post('my/bookings/{id}/reschedule-request', [MentorBookingController::class, 'requestReschedule']);
            Route::patch('my/bookings/{id}/complete', [MentorBookingController::class, 'completeMyBooking']);
            Route::get('my/bookings', [MentorBookingController::class, 'myBookings']);
            Route::get('my/sessions', [MentorBookingController::class, 'mySessions']);
            Route::get('my/session-credits', [MentorBookingController::class, 'mySessionCredits']);
            Route::get('my/prep-boards', [PrepBoardController::class, 'myList']);
            Route::get('my/prep-boards/{mentorId}', [PrepBoardController::class, 'myShow']);
            Route::put('my/prep-boards/{mentorId}', [PrepBoardController::class, 'myUpdate']);
            Route::post('my/prep-boards/{mentorId}/updates', [PrepBoardController::class, 'myAddUpdate']);
            Route::get('my/prep-boards/{mentorId}/daily-targets', [PrepBoardController::class, 'myListDailyTargets']);
            Route::get('my/prep-boards/{mentorId}/daily-targets/{date}', [PrepBoardController::class, 'myShowDailyTarget'])
                ->where('date', '[0-9]{4}-[0-9]{2}-[0-9]{2}');
            Route::get('my/prep-boards/{mentorId}/target-cycles/{startDate}', [PrepBoardController::class, 'myShowTargetCycle'])
                ->where('startDate', '[0-9]{4}-[0-9]{2}-[0-9]{2}');
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

        Route::get('documents', [MentorDocumentManagementController::class, 'index']);
        Route::post('documents', [MentorDocumentManagementController::class, 'store']);
        Route::put('documents/{id}', [MentorDocumentManagementController::class, 'update']);
        Route::patch('documents/{id}/enabled', [MentorDocumentManagementController::class, 'toggleEnabled']);
        Route::delete('documents/{id}', [MentorDocumentManagementController::class, 'destroy']);
        Route::post('documents/{id}/files', [MentorDocumentManagementController::class, 'uploadFile']);
        Route::delete('documents/{id}/files/{fileId}', [MentorDocumentManagementController::class, 'destroyFile']);

        Route::get('bookings/{id}', [MentorBookingController::class, 'showMentorBooking']);
        Route::get('bookings/{id}/history', [MentorBookingController::class, 'bookingHistory']);
        Route::get('bookings/{id}/ics', [MentorBookingController::class, 'downloadMentorIcs']);
        Route::post('bookings/{id}/cancel', [MentorBookingController::class, 'cancelMentorBooking']);
        Route::post('bookings/{id}/no-show', [MentorBookingController::class, 'markNoShow']);
        Route::post('bookings/{id}/request-feedback', [MentorBookingController::class, 'requestFeedback']);
        Route::get('bookings', [MentorBookingController::class, 'mentorBookings']);
        Route::get('sessions', [MentorBookingController::class, 'mentorSessions']);
        Route::get('session-credits', [MentorBookingController::class, 'mentorSessionCredits']);
        Route::patch('bookings/{id}/status', [MentorBookingController::class, 'updateStatus']);
        Route::patch('bookings/{id}/schedule', [MentorBookingController::class, 'updateSchedule']);
        Route::post('bookings/{id}/schedule', [MentorBookingController::class, 'scheduleMentorBooking']);
        Route::post('bookings/{id}/reschedule-approve', [MentorBookingController::class, 'approveReschedule']);
        Route::post('bookings/{id}/reschedule-reject', [MentorBookingController::class, 'rejectReschedule']);
        Route::patch('bookings/{id}/complete', [MentorBookingController::class, 'completeMentorBooking']);
        Route::get('demo-assignments', [\App\Http\Controllers\Api\V1\SessionChatController::class, 'mentorDemoAssignments']);
        Route::get('student-message-threads', [\App\Http\Controllers\Api\V1\SessionChatController::class, 'mentorThreads']);

        Route::get('prep-boards', [PrepBoardController::class, 'mentorList']);
        Route::get('prep-boards/{menteeUserId}', [PrepBoardController::class, 'mentorShow']);
        Route::put('prep-boards/{menteeUserId}', [PrepBoardController::class, 'mentorUpdate']);
        Route::post('prep-boards/{menteeUserId}/updates', [PrepBoardController::class, 'mentorAddUpdate']);
        Route::post('prep-boards/{menteeUserId}/updates/{updateId}/reply', [PrepBoardController::class, 'mentorReplyUpdate']);
        Route::get('prep-boards/{menteeUserId}/daily-targets', [PrepBoardController::class, 'mentorListDailyTargets']);
        Route::post('prep-boards/{menteeUserId}/daily-targets/copy', [PrepBoardController::class, 'mentorCopyDailyTarget']);
        Route::get('prep-boards/{menteeUserId}/daily-targets/{date}', [PrepBoardController::class, 'mentorShowDailyTarget'])
            ->where('date', '[0-9]{4}-[0-9]{2}-[0-9]{2}');
        Route::put('prep-boards/{menteeUserId}/daily-targets/{date}', [PrepBoardController::class, 'mentorUpsertDailyTarget'])
            ->where('date', '[0-9]{4}-[0-9]{2}-[0-9]{2}');
        Route::post('prep-boards/{menteeUserId}/target-cycles/copy', [PrepBoardController::class, 'mentorCopyTargetCycle']);
        Route::get('prep-boards/{menteeUserId}/target-cycles/{startDate}', [PrepBoardController::class, 'mentorShowTargetCycle'])
            ->where('startDate', '[0-9]{4}-[0-9]{2}-[0-9]{2}');
        Route::put('prep-boards/{menteeUserId}/target-cycles/{startDate}', [PrepBoardController::class, 'mentorSaveTargetCycle'])
            ->where('startDate', '[0-9]{4}-[0-9]{2}-[0-9]{2}');
        Route::post('prep-boards/{menteeUserId}/target-cycles/{startDate}/publish', [PrepBoardController::class, 'mentorPublishTargetCycle'])
            ->where('startDate', '[0-9]{4}-[0-9]{2}-[0-9]{2}');

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
