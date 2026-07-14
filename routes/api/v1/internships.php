<?php

use App\Http\Controllers\Api\V1\Internship\InternshipPublicController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'internships'], function () {
    Route::get('/', [InternshipPublicController::class, 'index']);
    Route::post('apply', [InternshipPublicController::class, 'apply']);
    Route::get('{slug}', [InternshipPublicController::class, 'show']);
    Route::post('{slug}/apply', [InternshipPublicController::class, 'apply']);
});
