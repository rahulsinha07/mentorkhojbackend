<?php

namespace App\Http\Controllers\Api\V1;

use App\CentralLogics\Helpers;
use App\CentralLogics\SessionChatLogic;
use App\CentralLogics\SessionChatMailLogic;
use App\Http\Controllers\Controller;
use App\Model\Mentor\Mentor;
use App\Model\SessionChatMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SessionChatController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $mentorProfile = SessionChatLogic::mentorProfileForUser((int) $user->id);
        $mentorId = (int) $request->query('mentor_id', 0);
        $menteeUserId = (int) $request->query('mentee_user_id', 0);

        if ($mentorProfile && $menteeUserId > 0 && ($mentorId === 0 || $mentorId === (int) $mentorProfile->id)) {
            $mentorId = (int) $mentorProfile->id;
            if (!SessionChatLogic::mentorCanAccess($mentorId, $menteeUserId)) {
                return response()->json(['errors' => [['message' => 'Chat not available']]], 403);
            }
        } else {
            if ($mentorId < 1) {
                return response()->json(['errors' => [['message' => 'mentor_id is required']]], 422);
            }
            $menteeUserId = (int) $user->id;
            if (!SessionChatLogic::studentCanAccess($menteeUserId, $mentorId)) {
                return response()->json(['errors' => [['message' => 'Chat not available']]], 403);
            }
        }

        $messages = SessionChatMessage::query()
            ->where('mentee_user_id', $menteeUserId)
            ->where('mentor_id', $mentorId)
            ->orderBy('id')
            ->limit(200)
            ->get()
            ->map(fn (SessionChatMessage $m) => SessionChatLogic::formatMessage($m))
            ->values();

        return response()->json(array_merge([
            'ok' => true,
            'mentee_user_id' => $menteeUserId,
            'mentor_id' => $mentorId,
            'chat_enabled' => true,
            'messages' => $messages,
        ], SessionChatLogic::quotaPayload($menteeUserId, $mentorId)));
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'mentor_id' => 'required|integer',
            'body' => 'required|string|max:2000',
            'mentee_user_id' => 'nullable|integer',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 422);
        }

        $body = trim((string) $request->input('body'));
        if ($body === '') {
            return response()->json(['errors' => [['message' => 'Message is required']]], 422);
        }
        if (SessionChatLogic::containsPii($body)) {
            return response()->json(['errors' => [['message' => SessionChatLogic::PII_ERROR]]], 422);
        }

        $user = $request->user();
        $mentorId = (int) $request->input('mentor_id');
        $mentorProfile = SessionChatLogic::mentorProfileForUser((int) $user->id);
        $asMentor = $mentorProfile
            && (int) $mentorProfile->id === $mentorId
            && $request->filled('mentee_user_id')
            && (int) $request->input('mentee_user_id') !== (int) $user->id;

        if ($asMentor) {
            $menteeUserId = (int) $request->input('mentee_user_id');
            if (!SessionChatLogic::mentorCanAccess($mentorId, $menteeUserId)) {
                return response()->json(['errors' => [['message' => 'Chat not available']]], 403);
            }
            $senderRole = 'mentor';
        } else {
            $menteeUserId = (int) $user->id;
            if (!SessionChatLogic::studentCanAccess($menteeUserId, $mentorId)) {
                return response()->json(['errors' => [['message' => 'Chat not available']]], 403);
            }
            $senderRole = 'mentee';
            $quota = SessionChatLogic::quotaPayload($menteeUserId, $mentorId);
            if (!$quota['student_can_send']) {
                return response()->json([
                    'errors' => [['message' => 'Free chat limit reached. Book a paid session for unlimited messages.']],
                ], 403);
            }
        }

        $related = SessionChatLogic::relatedIds($menteeUserId, $mentorId);
        $row = SessionChatMessage::create([
            'mentee_user_id' => $menteeUserId,
            'mentor_id' => $mentorId,
            'demo_booking_id' => $related['demo_booking_id'],
            'mentor_booking_id' => $related['mentor_booking_id'],
            'sender_role' => $senderRole,
            'body' => $body,
        ]);

        SessionChatMailLogic::notify($row);

        return response()->json(array_merge([
            'ok' => true,
            'message' => SessionChatLogic::formatMessage($row),
        ], SessionChatLogic::quotaPayload($menteeUserId, $mentorId)), 201);
    }

    public function mentorDemoAssignments(Request $request): JsonResponse
    {
        $mentor = Mentor::where('user_id', $request->user()->id)->first();
        if (!$mentor) {
            return response()->json(['errors' => [['message' => 'Mentor profile not found']]], 404);
        }

        return response()->json([
            'ok' => true,
            'assignments' => SessionChatLogic::assignmentsForMentor($mentor),
        ]);
    }
}
