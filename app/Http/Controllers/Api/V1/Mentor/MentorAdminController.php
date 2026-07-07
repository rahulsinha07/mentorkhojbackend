<?php

namespace App\Http\Controllers\Api\V1\Mentor;

use App\Http\Controllers\Controller;
use App\Model\Mentor\Mentor;
use App\Model\Mentor\MentorPayout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MentorAdminController extends Controller
{
    public function index(): JsonResponse
    {
        $mentors = Mentor::withCount(['services', 'bookings'])
            ->orderByDesc('created_at')
            ->paginate(50);

        return response()->json($mentors);
    }

    public function approvePayout(int $id): JsonResponse
    {
        $payout = MentorPayout::find($id);
        if (!$payout) {
            return response()->json(['errors' => [['message' => 'Payout not found']]], 404);
        }

        $payout->status = 'approved';
        $payout->save();

        return response()->json(['message' => 'Payout approved', 'payout' => $payout]);
    }
}
