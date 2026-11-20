<?php

use App\Http\Controllers\Api\V1\Tools\JeeRankPredictorController;
use Illuminate\Support\Facades\Route;

// Public SEO tools — no auth, normal throttle applies.
Route::get('tools/jee-rank-predictor/sessions', [JeeRankPredictorController::class, 'sessions']);
Route::post('tools/jee-rank-predictor', [JeeRankPredictorController::class, 'estimate']);
