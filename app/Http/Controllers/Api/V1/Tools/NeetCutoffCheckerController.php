<?php

namespace App\Http\Controllers\Api\V1\Tools;

use App\Model\NeetCategoryCutoff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class NeetCutoffCheckerController extends Controller
{
    /** Latest year's cutoffs, for the frontend to render the reference table / dropdown. */
    public function cutoffs(): JsonResponse
    {
        $latestYear = NeetCategoryCutoff::max('year');

        if (!$latestYear) {
            return response()->json(['available' => false, 'cutoffs' => []]);
        }

        $cutoffs = NeetCategoryCutoff::where('year', $latestYear)
            ->orderBy('cutoff_marks_low')
            ->get(['category', 'percentile', 'cutoff_marks_low', 'cutoff_marks_high']);

        return response()->json(['available' => true, 'year' => $latestYear, 'cutoffs' => $cutoffs]);
    }

    public function check(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'score' => 'required|integer|min:0|max:720',
            'category' => 'required|string|in:general,ews,obc_ncl,sc,st',
            'year' => 'nullable|integer',
        ]);

        $year = $validated['year'] ?? NeetCategoryCutoff::max('year');

        $cutoff = NeetCategoryCutoff::where('year', $year)
            ->where('category', $validated['category'])
            ->first();

        if (!$cutoff) {
            return response()->json([
                'message' => 'No cutoff data for that year/category yet.',
            ], 422);
        }

        $qualifies = $validated['score'] >= $cutoff->cutoff_marks_low;

        return response()->json([
            'year' => $cutoff->year,
            'category' => $cutoff->category,
            'score' => $validated['score'],
            'qualifies' => $qualifies,
            'cutoff_marks_low' => $cutoff->cutoff_marks_low,
            'cutoff_marks_high' => $cutoff->cutoff_marks_high,
            'percentile' => $cutoff->percentile,
            'methodology' => "NEET (UG) {$cutoff->year} qualifying cutoff for this category was the {$cutoff->percentile}th percentile "
                . "({$cutoff->cutoff_marks_low}\u{2013}{$cutoff->cutoff_marks_high} out of 720), as published by NTA. "
                . 'This checks whether you crossed the minimum qualifying mark — it is not a rank or college prediction.',
        ]);
    }
}
