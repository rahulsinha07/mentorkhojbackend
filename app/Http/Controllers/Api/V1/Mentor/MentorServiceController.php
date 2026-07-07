<?php

namespace App\Http\Controllers\Api\V1\Mentor;

use App\CentralLogics\Helpers;
use App\CentralLogics\MentorLogic;
use App\Http\Controllers\Controller;
use App\Model\Mentor\Mentor;
use App\Model\Mentor\MentorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MentorServiceController extends Controller
{
    private function mentorForUser(Request $request): ?Mentor
    {
        return Mentor::where('user_id', $request->user()->id)->first();
    }

    public function index(Request $request): JsonResponse
    {
        $mentor = $this->mentorForUser($request);
        if (!$mentor) {
            return response()->json(['errors' => [['message' => 'Mentor profile not found']]], 404);
        }

        return response()->json([
            'services' => $mentor->services->map(fn ($s) => MentorLogic::formatService($s))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $mentor = $this->mentorForUser($request);
        if (!$mentor) {
            return response()->json(['errors' => [['message' => 'Mentor profile not found']]], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'duration_minutes' => 'required|integer|min:5|max:480',
            'price' => 'required|numeric|min:0',
            'compare_at_price' => 'nullable|numeric|min:0',
            'badge' => 'nullable|in:best_seller,best_deal',
            'is_popular' => 'nullable|boolean',
            'meeting_type' => 'nullable|string|max:32',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $maxOrder = $mentor->services()->max('sort_order') ?? 0;

        $service = MentorService::create([
            'mentor_id' => $mentor->id,
            'title' => $request->title,
            'description' => $request->description,
            'duration_minutes' => $request->duration_minutes,
            'price' => $request->price,
            'compare_at_price' => $request->compare_at_price,
            'badge' => $request->badge,
            'is_enabled' => true,
            'is_popular' => (bool) $request->input('is_popular', false),
            'sort_order' => $maxOrder + 1,
            'meeting_type' => $request->input('meeting_type', 'video'),
        ]);

        return response()->json([
            'message' => 'Service created',
            'service' => MentorLogic::formatService($service),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $mentor = $this->mentorForUser($request);
        if (!$mentor) {
            return response()->json(['errors' => [['message' => 'Mentor profile not found']]], 404);
        }

        $service = MentorService::where('mentor_id', $mentor->id)->where('id', $id)->first();
        if (!$service) {
            return response()->json(['errors' => [['message' => 'Service not found']]], 404);
        }

        $fields = ['title', 'description', 'duration_minutes', 'price', 'compare_at_price', 'badge', 'is_popular', 'sort_order', 'meeting_type'];
        foreach ($fields as $field) {
            if ($request->has($field)) {
                $service->{$field} = $request->input($field);
            }
        }
        $service->save();

        return response()->json([
            'message' => 'Service updated',
            'service' => MentorLogic::formatService($service),
        ]);
    }

    public function toggleEnabled(Request $request, int $id): JsonResponse
    {
        $mentor = $this->mentorForUser($request);
        if (!$mentor) {
            return response()->json(['errors' => [['message' => 'Mentor profile not found']]], 404);
        }

        $service = MentorService::where('mentor_id', $mentor->id)->where('id', $id)->first();
        if (!$service) {
            return response()->json(['errors' => [['message' => 'Service not found']]], 404);
        }

        $service->is_enabled = (bool) $request->input('is_enabled', !$service->is_enabled);
        $service->save();

        return response()->json([
            'message' => 'Service updated',
            'service' => MentorLogic::formatService($service),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $mentor = $this->mentorForUser($request);
        if (!$mentor) {
            return response()->json(['errors' => [['message' => 'Mentor profile not found']]], 404);
        }

        $service = MentorService::where('mentor_id', $mentor->id)->where('id', $id)->first();
        if (!$service) {
            return response()->json(['errors' => [['message' => 'Service not found']]], 404);
        }

        $service->is_enabled = false;
        $service->save();

        return response()->json(['message' => 'Service disabled']);
    }
}
