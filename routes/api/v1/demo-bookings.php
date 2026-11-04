<?php

use App\Http\Controllers\Api\V1\DemoBookingController;
use App\Http\Controllers\Api\V1\SessionChatController;
use Illuminate\Support\Facades\Route;

// Public create — no auth (free demo form from LPs)
Route::post('demo-bookings', [DemoBookingController::class, 'store']);

// Optional logged-in list
Route::middleware('auth:api')->group(function () {
    Route::get('demo-bookings/my', [DemoBookingController::class, 'my']);
    Route::get('session-chats/threads', [SessionChatController::class, 'threads']);
    Route::get('session-chats/unread-count', [SessionChatController::class, 'unreadCount']);
    Route::post('session-chats/mark-read', [SessionChatController::class, 'markRead']);
    Route::get('session-chats', [SessionChatController::class, 'index']);
    Route::post('session-chats', [SessionChatController::class, 'store']);
    Route::post('session-chats/notify-whatsapp', [SessionChatController::class, 'notifyWhatsapp']);
    Route::get('session-chats/messages/{id}/attachment', [SessionChatController::class, 'downloadAttachment']);
});
