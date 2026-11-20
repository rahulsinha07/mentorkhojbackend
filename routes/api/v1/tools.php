<?php

use App\Http\Controllers\Api\V1\Tools\JeeCollegePredictorController;
use App\Http\Controllers\Api\V1\Tools\JeeRankPredictorController;
use App\Http\Controllers\Api\V1\Tools\NeetCutoffCheckerController;
use Illuminate\Support\Facades\Route;

// Public SEO tools — no auth, normal throttle applies.
Route::get('tools/jee-rank-predictor/sessions', [JeeRankPredictorController::class, 'sessions']);
Route::post('tools/jee-rank-predictor', [JeeRankPredictorController::class, 'estimate']);

Route::get('tools/jee-college-predictor/meta', [JeeCollegePredictorController::class, 'meta']);
Route::post('tools/jee-college-predictor', [JeeCollegePredictorController::class, 'predict']);

Route::get('tools/neet-cutoff-checker/cutoffs', [NeetCutoffCheckerController::class, 'cutoffs']);
Route::post('tools/neet-cutoff-checker', [NeetCutoffCheckerController::class, 'check']);
