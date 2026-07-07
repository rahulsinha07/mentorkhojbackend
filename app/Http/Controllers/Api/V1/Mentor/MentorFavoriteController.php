<?php

namespace App\Http\Controllers\Api\V1\Mentor;

use App\CentralLogics\MentorLogic;
use App\Http\Controllers\Controller;
use App\Model\Mentor\Mentor;
use App\Model\Mentor\MentorFavorite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MentorFavoriteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $favorites = MentorFavorite::where('user_id', $request->user()->id)
            ->with(['mentor.enabledServices'])
            ->latest()
            ->get();

        $mentors = $favorites
            ->map(fn (MentorFavorite $f) => $f->mentor)
            ->filter(fn (?Mentor $m) => $m && $m->is_published)
            ->map(fn (Mentor $m) => MentorLogic::formatPublic($m))
            ->values();

        return response()->json([
            'favorites' => $mentors,
            'total' => $mentors->count(),
        ]);
    }

    public function store(Request $request, int $id): JsonResponse
    {
        $mentor = Mentor::published()->find($id);
        if (!$mentor) {
            return response()->json(['errors' => [['message' => 'Mentor not found']]], 404);
        }

        MentorFavorite::firstOrCreate([
            'user_id' => $request->user()->id,
            'mentor_id' => $mentor->id,
        ]);

        return response()->json(['message' => 'Added to favorites'], 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        MentorFavorite::where('user_id', $request->user()->id)
            ->where('mentor_id', $id)
            ->delete();

        return response()->json(['message' => 'Removed from favorites']);
    }
}
