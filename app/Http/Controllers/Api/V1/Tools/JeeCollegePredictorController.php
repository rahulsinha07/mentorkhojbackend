<?php

namespace App\Http\Controllers\Api\V1\Tools;

use App\Model\CollegeCutoff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class JeeCollegePredictorController extends Controller
{
    private const EXAM_TYPE = 'jee_main';

    /** Rows kept per confidence band — guarantees a spread instead of one band drowning out the others. */
    private const PER_BAND_LIMIT = 150;

    /** How far below the user's rank a closing rank can still be shown as a reach ("ambitious"). */
    private const AMBITIOUS_MARGIN = 0.20;
    private const SAFE_MARGIN = 0.15;

    public function meta(): JsonResponse
    {
        $latest = CollegeCutoff::query()
            ->examType(self::EXAM_TYPE)
            ->orderByDesc('year')
            ->orderByDesc('round')
            ->first(['year', 'round']);

        if (!$latest) {
            return response()->json(['available' => false]);
        }

        return response()->json([
            'available' => true,
            'year' => $latest->year,
            'round' => $latest->round,
        ]);
    }

    public function predict(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rank' => 'required|integer|min:1',
            'category' => 'required|string|in:open,ews,obc_ncl,sc,st',
            'gender' => 'required|string|in:gender_neutral,female',
            'pwd' => 'nullable|boolean',
        ]);

        $rank = (int) $validated['rank'];
        $pwd = (bool) ($validated['pwd'] ?? false);

        // A lower closing rank means fewer candidates were admitted (more competitive).
        // Query each confidence band separately and cap each on its own — otherwise a
        // single global ORDER BY + LIMIT lets dense ambitious-end rows crowd out every
        // moderate/safe option for categories with fewer total matches.
        $ambitiousFloor = max(1, (int) floor($rank * (1 - self::AMBITIOUS_MARGIN)));
        $moderateFloor = max(1, (int) floor($rank * (1 - self::SAFE_MARGIN)));
        $safeFloor = (int) ceil($rank * (1 + self::SAFE_MARGIN));

        $baseQuery = fn () => CollegeCutoff::query()
            ->latestBatch(self::EXAM_TYPE)
            ->where('category', $validated['category'])
            ->where('gender', $validated['gender'])
            ->where('is_pwd', $pwd)
            ->where('is_preparatory', false)
            ->whereNotNull('closing_rank');

        $ambitious = $baseQuery()
            ->whereBetween('closing_rank', [$ambitiousFloor, max($ambitiousFloor, $moderateFloor - 1)])
            ->orderByDesc('closing_rank') // closest-to-reach first
            ->limit(self::PER_BAND_LIMIT)
            ->get();

        $moderate = $baseQuery()
            ->whereBetween('closing_rank', [$moderateFloor, max($moderateFloor, $safeFloor - 1)])
            ->orderBy('closing_rank')
            ->limit(self::PER_BAND_LIMIT)
            ->get();

        $safe = $baseQuery()
            ->where('closing_rank', '>=', $safeFloor)
            ->orderBy('closing_rank') // closest-to-moderate (most relevant) safe options first
            ->limit(self::PER_BAND_LIMIT)
            ->get();

        $results = $ambitious->concat($moderate)->concat($safe)->map(function (CollegeCutoff $row) use ($rank) {
            $confidence = match (true) {
                $row->closing_rank >= $rank * (1 + self::SAFE_MARGIN) => 'safe',
                $row->closing_rank >= $rank * (1 - self::SAFE_MARGIN) => 'moderate',
                default => 'ambitious',
            };

            return [
                'institute_type' => $row->institute_type,
                'institute_name' => $row->institute_name,
                'program' => $row->program,
                'quota' => $row->quota,
                'opening_rank' => $row->opening_rank,
                'closing_rank' => $row->closing_rank,
                'confidence' => $confidence,
            ];
        });

        $latest = CollegeCutoff::query()
            ->examType(self::EXAM_TYPE)
            ->orderByDesc('year')
            ->orderByDesc('round')
            ->first(['year', 'round']);

        return response()->json([
            'rank' => $rank,
            'year' => $latest->year ?? null,
            'round' => $latest->round ?? null,
            'results' => $results,
            'methodology' => 'Based on the final-round closing ranks from the most recent year loaded. '
                . 'Home State / Other State quota rows (HS/OS) only apply if your domicile matches that '
                . "institute's state. This is a reference point from last year's settled counselling, not a guarantee.",
        ]);
    }
}
