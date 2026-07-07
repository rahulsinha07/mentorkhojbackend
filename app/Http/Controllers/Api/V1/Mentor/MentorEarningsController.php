<?php

namespace App\Http\Controllers\Api\V1\Mentor;

use App\CentralLogics\Helpers;
use App\CentralLogics\MentorEarningsLogic;
use App\Http\Controllers\Controller;
use App\Model\Mentor\Mentor;
use App\Model\Mentor\MentorEarning;
use App\Model\Mentor\MentorPayout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MentorEarningsController extends Controller
{
    private function mentorForUser(Request $request): ?Mentor
    {
        return Mentor::where('user_id', $request->user()->id)->first();
    }

    public function summary(Request $request): JsonResponse
    {
        $mentor = $this->mentorForUser($request);
        if (!$mentor) {
            return response()->json(['errors' => [['message' => 'Mentor profile not found']]], 404);
        }

        return response()->json(MentorEarningsLogic::summary($mentor));
    }

    public function transactions(Request $request): JsonResponse
    {
        $mentor = $this->mentorForUser($request);
        if (!$mentor) {
            return response()->json(['errors' => [['message' => 'Mentor profile not found']]], 404);
        }

        $transactions = MentorEarning::where('mentor_id', $mentor->id)
            ->latest()
            ->paginate(30);

        return response()->json([
            'transactions' => $transactions->items(),
            'total' => $transactions->total(),
        ]);
    }

    public function requestPayout(Request $request): JsonResponse
    {
        $mentor = $this->mentorForUser($request);
        if (!$mentor) {
            return response()->json(['errors' => [['message' => 'Mentor profile not found']]], 404);
        }

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
            'method' => 'nullable|string|max:64',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $summary = MentorEarningsLogic::summary($mentor);
        if ($request->amount > $summary['available_balance']) {
            return response()->json(['errors' => [['message' => 'Insufficient balance']]], 403);
        }

        $settings = $mentor->settings;
        $payout = MentorPayout::create([
            'mentor_id' => $mentor->id,
            'amount' => $request->amount,
            'method' => $request->method ?? 'bank_transfer',
            'bank_details' => $settings?->payout_details,
            'status' => 'pending',
        ]);

        return response()->json(['message' => 'Payout requested', 'payout' => $payout], 201);
    }

    public function payouts(Request $request): JsonResponse
    {
        $mentor = $this->mentorForUser($request);
        if (!$mentor) {
            return response()->json(['errors' => [['message' => 'Mentor profile not found']]], 404);
        }

        $payouts = MentorPayout::where('mentor_id', $mentor->id)->latest()->get();
        return response()->json(['payouts' => $payouts]);
    }
}
