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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            ->limit(500)
            ->get()
            ->map(fn (SessionChatMessage $m) => SessionChatLogic::formatMessage($m))
            ->values();

        SessionChatLogic::markThreadRead((int) $user->id, $mentorId, $menteeUserId);

        $quota = SessionChatLogic::quotaPayload($menteeUserId, $mentorId);

        return response()->json(array_merge([
            'ok' => true,
            'mentee_user_id' => $menteeUserId,
            'mentor_id' => $mentorId,
            'chat_enabled' => true,
            'messages' => $messages,
            'unread_count' => 0,
        ], $quota));
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'mentor_id' => 'required|integer',
            'body' => 'nullable|string|max:2000',
            'mentee_user_id' => 'nullable|integer',
            'attachment' => 'nullable|file|max:'.SessionChatLogic::ATTACHMENT_MAX_KB,
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 422);
        }

        $body = trim((string) $request->input('body', ''));
        $file = $request->file('attachment');
        $stored = null;

        if ($body === '' && !$file) {
            return response()->json(['errors' => [['message' => 'Message or attachment is required']]], 422);
        }

        if ($body !== '' && SessionChatLogic::containsPii($body)) {
            return response()->json(['errors' => [['message' => SessionChatLogic::PII_ERROR]]], 422);
        }

        if ($file) {
            $stored = SessionChatLogic::storeAttachment($file);
            if (!$stored) {
                return response()->json(['errors' => [['message' => 'File type not allowed']]], 422);
            }
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
                SessionChatLogic::deleteAttachment($stored['path'] ?? null);

                return response()->json(['errors' => [['message' => 'Chat not available']]], 403);
            }
            $senderRole = 'mentor';
        } else {
            $menteeUserId = (int) $user->id;
            if (!SessionChatLogic::studentCanAccess($menteeUserId, $mentorId)) {
                SessionChatLogic::deleteAttachment($stored['path'] ?? null);

                return response()->json(['errors' => [['message' => 'Chat not available']]], 403);
            }
            $senderRole = 'mentee';
        }

        $entitlement = SessionChatLogic::messagingEntitlement($menteeUserId, $mentorId);
        if (!($entitlement['active'] ?? false)) {
            SessionChatLogic::deleteAttachment($stored['path'] ?? null);
            $disabled = SessionChatLogic::messagingDisabledPayload();

            return response()->json([
                'errors' => [['message' => $entitlement['disabled_message'] ?? $disabled['message']]],
                'messaging_active' => false,
                'messaging_disabled_message' => $entitlement['disabled_message'] ?? $disabled['message'],
                'admin_whatsapp' => $entitlement['admin_whatsapp'] ?? $disabled['admin_whatsapp'],
                'admin_whatsapp_display' => $entitlement['admin_whatsapp_display'] ?? $disabled['admin_whatsapp_display'],
                'admin_whatsapp_url' => $entitlement['admin_whatsapp_url'] ?? $disabled['admin_whatsapp_url'],
            ], 403);
        }

        if ($senderRole === 'mentee') {
            $quota = SessionChatLogic::quotaPayload($menteeUserId, $mentorId);
            if (!$quota['student_can_send']) {
                SessionChatLogic::deleteAttachment($stored['path'] ?? null);

                return response()->json([
                    'errors' => [['message' => 'Free chat limit reached. Book a paid session for unlimited messages.']],
                ], 403);
            }
        }

        if ($stored && !SessionChatLogic::isPaidUnlimited($menteeUserId, $mentorId)) {
            SessionChatLogic::deleteAttachment($stored['path'] ?? null);

            return response()->json([
                'errors' => [['message' => 'Attachments are only available with a paid session.']],
            ], 403);
        }

        $related = SessionChatLogic::relatedIds($menteeUserId, $mentorId);
        $row = SessionChatMessage::create([
            'mentee_user_id' => $menteeUserId,
            'mentor_id' => $mentorId,
            'demo_booking_id' => $related['demo_booking_id'],
            'mentor_booking_id' => $related['mentor_booking_id'],
            'sender_role' => $senderRole,
            'body' => $body,
            'attachment_path' => $stored['path'] ?? null,
            'attachment_original_name' => $stored['original_name'] ?? null,
            'attachment_mime' => $stored['mime'] ?? null,
            'attachment_size' => $stored['size'] ?? null,
        ]);

        SessionChatLogic::markThreadRead((int) $user->id, $mentorId, $menteeUserId);
        SessionChatMailLogic::notify($row);

        return response()->json(array_merge([
            'ok' => true,
            'message' => SessionChatLogic::formatMessage($row),
        ], SessionChatLogic::quotaPayload($menteeUserId, $mentorId)), 201);
    }

    public function downloadAttachment(Request $request, int $id): StreamedResponse|JsonResponse
    {
        $message = SessionChatMessage::query()->find($id);
        if (!$message || !$message->attachment_path) {
            return response()->json(['errors' => [['message' => 'Attachment not found']]], 404);
        }

        if (!SessionChatLogic::userCanAccessMessage((int) $request->user()->id, $message)) {
            return response()->json(['errors' => [['message' => 'Chat not available']]], 403);
        }

        if (!Storage::disk('local')->exists($message->attachment_path)) {
            return response()->json(['errors' => [['message' => 'Attachment not found']]], 404);
        }

        $name = $message->attachment_original_name ?: 'attachment';
        $mime = $message->attachment_mime ?: 'application/octet-stream';

        return Storage::disk('local')->download($message->attachment_path, $name, [
            'Content-Type' => $mime,
        ]);
    }

    public function markRead(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'mentor_id' => 'required|integer',
            'mentee_user_id' => 'nullable|integer',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 422);
        }

        $user = $request->user();
        $mentorId = (int) $request->input('mentor_id');
        $mentorProfile = SessionChatLogic::mentorProfileForUser((int) $user->id);

        if ($mentorProfile && (int) $mentorProfile->id === $mentorId && $request->filled('mentee_user_id')) {
            $menteeUserId = (int) $request->input('mentee_user_id');
            if (!SessionChatLogic::mentorCanAccess($mentorId, $menteeUserId)) {
                return response()->json(['errors' => [['message' => 'Chat not available']]], 403);
            }
        } else {
            $menteeUserId = (int) $user->id;
            if (!SessionChatLogic::studentCanAccess($menteeUserId, $mentorId)) {
                return response()->json(['errors' => [['message' => 'Chat not available']]], 403);
            }
        }

        SessionChatLogic::markThreadRead((int) $user->id, $mentorId, $menteeUserId);

        return response()->json([
            'ok' => true,
            'unread_count' => 0,
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $as = $request->query('as');
        $as = is_string($as) ? $as : null;

        return response()->json([
            'ok' => true,
            'unread_count' => SessionChatLogic::totalUnreadForViewer((int) $request->user()->id, $as),
        ]);
    }

    public function threads(Request $request): JsonResponse
    {
        $menteeUserId = (int) $request->user()->id;

        return response()->json([
            'ok' => true,
            'threads' => SessionChatLogic::threadsForMentee($menteeUserId),
        ]);
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

    public function mentorThreads(Request $request): JsonResponse
    {
        $mentor = Mentor::where('user_id', $request->user()->id)->first();
        if (!$mentor) {
            return response()->json(['errors' => [['message' => 'Mentor profile not found']]], 404);
        }

        return response()->json([
            'ok' => true,
            'threads' => SessionChatLogic::threadsForMentor((int) $mentor->id),
        ]);
    }
}
