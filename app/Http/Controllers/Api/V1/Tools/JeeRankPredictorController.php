<?php

namespace App\Http\Controllers\Api\V1\Tools;

use App\Model\ExamSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class JeeRankPredictorController extends Controller
{
    private const EXAM_TYPE = 'jee_main';

    /**
     * Range padding around the point estimate. NTA percentiles are computed
     * to 7 decimal places but ties, rounding and session-to-session spread
     * mean a single-number rank claim overstates precision we don't have.
     */
    private const RANGE_PADDING = 0.05;

    /** List of sessions with real candidate counts, for the frontend dropdown. */
    public function sessions(): JsonResponse
    {
        $sessions = ExamSession::query()
            ->examType(self::EXAM_TYPE)
            ->orderByDesc('year')
            ->orderByDesc('session')
            ->get(['year', 'session', 'total_candidates'])
            ->map(fn (ExamSession $s) => [
                'year' => $s->year,
                'session' => $s->session,
                'total_candidates' => $s->total_candidates,
            ]);

        return response()->json(['sessions' => $sessions]);
    }

    public function estimate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'percentile' => 'required|numeric|min:0|max:100',
            'year' => 'required|integer',
            'session' => 'required|string|in:january,april',
        ]);

        $examSession = ExamSession::query()
            ->examType(self::EXAM_TYPE)
            ->where('year', $validated['year'])
            ->where('session', $validated['session'])
            ->first();

        if (!$examSession) {
            return response()->json([
                'message' => 'No candidate data for that session yet. Pick a session from the list.',
            ], 422);
        }

        $percentile = (float) $validated['percentile'];
        $totalCandidates = $examSession->total_candidates;

        // NTA's own definition of percentile: the percentage of candidates
        // who scored equal to or below a given raw score in that session.
        $pointEstimate = (int) round($totalCandidates * (100 - $percentile) / 100) + 1;

        $low = max(1, (int) floor($pointEstimate * (1 - self::RANGE_PADDING)));
        $high = (int) ceil($pointEstimate * (1 + self::RANGE_PADDING));

        return response()->json([
            'exam_type' => self::EXAM_TYPE,
            'year' => $examSession->year,
            'session' => $examSession->session,
            'percentile' => $percentile,
            'total_candidates_considered' => $totalCandidates,
            'estimated_rank_low' => $low,
            'estimated_rank_high' => $high,
            'methodology' => 'Estimated from NTA\'s published percentile definition '
                . '(percentage of candidates scoring at or below yours) and the total '
                . 'candidates who appeared in this session. This is an unofficial estimate, '
                . 'not an NTA-issued rank.',
        ]);
    }
}
