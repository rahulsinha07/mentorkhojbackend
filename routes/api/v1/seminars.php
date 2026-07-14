<?php

use App\Http\Controllers\Api\V1\Seminar\SeminarPublicController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'seminars'], function () {
    Route::get('/', [SeminarPublicController::class, 'index']);
    Route::get('{slug}', [SeminarPublicController::class, 'show']);
    Route::post('{slug}/register', [SeminarPublicController::class, 'register']);
});
