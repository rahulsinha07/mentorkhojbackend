<?php

use App\Http\Controllers\Api\V1\Tools\JeeCollegePredictorController;
use App\Http\Controllers\Api\V1\Tools\JeeRankPredictorController;
use App\Http\Controllers\Api\V1\Tools\NeetCutoffCheckerController;
use Illuminate\Support\Facades\Route;

// Public SEO tools — no auth.
// The *-rendering* GET endpoints below (sessions/meta/cutoffs lists) are read-only
// reference data fetched by the Next.js site at build time for every one of its
// ~550 statically generated pages. The default throttle:180,1 is tuned for live
// user traffic and was tripping repeatedly during full-site builds (one build
// server IP, hundreds of requests in a short burst), crashing the deploy outright.
// The calculator endpoints below (estimate/predict/check) do real per-request work
// and are only ever called client-side from a live page, so they keep the normal
// throttle.
Route::get('tools/jee-rank-predictor/sessions', [JeeRankPredictorController::class, 'sessions'])
    ->withoutMiddleware('throttle');
Route::post('tools/jee-rank-predictor', [JeeRankPredictorController::class, 'estimate']);

Route::get('tools/jee-college-predictor/meta', [JeeCollegePredictorController::class, 'meta'])
    ->withoutMiddleware('throttle');
Route::post('tools/jee-college-predictor', [JeeCollegePredictorController::class, 'predict']);

Route::get('tools/neet-cutoff-checker/cutoffs', [NeetCutoffCheckerController::class, 'cutoffs'])
    ->withoutMiddleware('throttle');
Route::post('tools/neet-cutoff-checker', [NeetCutoffCheckerController::class, 'check']);
