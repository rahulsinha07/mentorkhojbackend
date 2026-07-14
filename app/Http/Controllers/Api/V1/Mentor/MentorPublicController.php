<?php

namespace App\Http\Controllers\Api\V1\Mentor;

use App\CentralLogics\Helpers;
use App\CentralLogics\MentorLogic;
use App\Http\Controllers\Controller;
use App\Model\Mentor\Mentor;
use App\Model\Mentor\MentorShareTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MentorPublicController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Mentor::published()->with('enabledServices');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('display_name', 'like', "%{$search}%")
                    ->orWhere('headline', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category_id')) {
            $catId = (int) $request->category_id;
            $query->where(function ($q) use ($catId) {
                $q->where('category_ids', 'like', '%"id":' . $catId . '%')
                    ->orWhere('category_ids', 'like', '%"id":"' . $catId . '"%');
            });
        }

        $limit = (int) ($request->limit ?? 20);
        $offset = (int) ($request->offset ?? 0);

        $total = $query->count();
        $mentors = $query->orderByDesc('view_count')
            ->skip($offset)
            ->take($limit)
            ->get()
            ->map(fn ($m) => MentorLogic::formatPublic($m));

        return response()->json([
            'total_size' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'mentors' => $mentors,
        ]);
    }

    public function show(string $usernameOrId): JsonResponse
    {
        $mentor = MentorLogic::resolveMentor($usernameOrId);
        if (!$mentor || !$mentor->is_published || $mentor->status !== 'active') {
            return response()->json(['errors' => [['code' => 'not_found', 'message' => 'Mentor not found']]], 404);
        }

        $mentor->increment('view_count');
        $mentor->load('enabledServices');

        return response()->json(MentorLogic::formatPublic($mentor));
    }

    public function services(string $usernameOrId): JsonResponse
    {
        $mentor = MentorLogic::resolveMentor($usernameOrId);
        if (!$mentor || !$mentor->is_published) {
            return response()->json(['errors' => [['code' => 'not_found', 'message' => 'Mentor not found']]], 404);
        }

        return response()->json([
            'services' => $mentor->enabledServices->map(fn ($s) => MentorLogic::formatService($s))->values(),
        ]);
    }

    public function checkUsername(string $username): JsonResponse
    {
        $available = !Mentor::where('username', $username)->exists();
        return response()->json(['username' => $username, 'available' => $available]);
    }

    public function shareTemplates(): JsonResponse
    {
        $templates = MentorShareTemplate::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'slug' => $t->slug,
                'title' => $t->title,
                'subtitle' => $t->subtitle,
                'poster_image' => $t->poster_image,
            ]);

        return response()->json(['templates' => $templates]);
    }
}
