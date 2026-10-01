<?php

namespace App\CentralLogics;

use App\Model\DemoBooking;
use App\Model\Mentor\Mentor;
use App\Model\Mentor\MentorBooking;
use App\Model\Mentor\MentorSessionCredit;
use App\Model\Mentor\MentorSessionCreditLedger;
use App\Model\SessionChatMessage;
use App\Model\SessionChatMessagingOverride;
use App\Model\SessionChatRead;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

class SessionChatLogic
{
    public const FREE_STUDENT_LIMIT = 5;
    public const PII_ERROR = 'cannot send this as it has PII';
    public const ATTACHMENT_MAX_KB = 10240;

    private const BLOCKED_EXTENSIONS = [
        'php', 'phtml', 'php3', 'php4', 'php5', 'phar',
        'exe', 'bat', 'cmd', 'com', 'sh', 'bash',
        'js', 'html', 'htm', 'svg',
    ];

    public static function firstName(?string $name): string
    {
        $t = trim((string) $name);
        if ($t === '') {
            return 'Student';
        }
        $parts = preg_split('/\s+/', $t) ?: [];

        return $parts[0] !== '' ? $parts[0] : 'Student';
    }

    public static function containsPii(string $body): bool
    {
        if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $body)) {
            return true;
        }

        $compact = preg_replace('/[\s\-\(\)\.]/', '', $body) ?? '';
        if (preg_match('/(?:\+?91)?[6-9]\d{9}/', $compact)) {
            return true;
        }

        return false;
    }

    public static function supportMentorUsername(): string
    {
        return (string) config('session_chat.support_mentor_username', 'mentorkhoj-support');
    }

    public static function supportMentorDisplayName(): string
    {
        return (string) config('session_chat.support_mentor_display_name', 'ADMIN');
    }

    /**
     * Ensure the MentorKhoj ADMIN/support mentor exists; return its id.
     */
    public static function ensureSupportMentorId(): int
    {
        $configured = config('session_chat.support_mentor_id');
        if ($configured) {
            $id = (int) $configured;
            if ($id > 0 && Mentor::query()->where('id', $id)->exists()) {
                return $id;
            }
        }

        $username = self::supportMentorUsername();
        $existing = Mentor::query()->where('username', $username)->first();
        if ($existing) {
            return (int) $existing->id;
        }

        $mentor = Mentor::create([
            'user_id' => null,
            'username' => $username,
            'display_name' => self::supportMentorDisplayName(),
            'headline' => 'MentorKhoj support',
            'bio_html' => null,
            'status' => 'active',
            'is_published' => false,
            'category_ids' => json_encode([]),
            'images' => json_encode(['default.png']),
            'social_links' => null,
        ]);

        return (int) $mentor->id;
    }

    public static function isSupportMentor(int $mentorId): bool
    {
        if ($mentorId < 1) {
            return false;
        }
        $configured = config('session_chat.support_mentor_id');
        if ($configured && (int) $configured === $mentorId) {
            return true;
        }

        return Mentor::query()
            ->where('id', $mentorId)
            ->where('username', self::supportMentorUsername())
            ->exists();
    }

    /**
     * True when this pair has (or had) a paid mentorship relationship that is
     * subject to the timed messaging window (pack / paid booking / paid demo).
     */
    public static function hasPaidMessagingRelationship(int $menteeUserId, int $mentorId): bool
    {
        if (self::isSupportMentor($mentorId)) {
            return false;
        }

        $paidPaid = MentorBooking::query()
            ->where('mentee_user_id', $menteeUserId)
            ->where('mentor_id', $mentorId)
            ->where('payment_status', 'paid')
            ->get()
            ->contains(function (MentorBooking $b) {
                return ((float) $b->amount + (float) $b->tax_amount) > 0;
            });
        if ($paidPaid) {
            return true;
        }

        $creditExists = MentorSessionCredit::query()
            ->where('mentee_user_id', $menteeUserId)
            ->where('mentor_id', $mentorId)
            ->where('credits_total', '>', 0)
            ->exists();
        if ($creditExists) {
            return true;
        }

        $demoIds = self::menteeDemoIds($menteeUserId);
        if ($demoIds->isEmpty()) {
            return false;
        }

        return DB::table('demo_booking_mentors')
            ->whereIn('demo_booking_id', $demoIds)
            ->where('mentor_id', $mentorId)
            ->where('paid_session_done', 1)
            ->exists();
    }

    public static function isPaidUnlimited(int $menteeUserId, int $mentorId): bool
    {
        if (self::isSupportMentor($mentorId)) {
            return true;
        }

        if (!self::hasPaidMessagingRelationship($menteeUserId, $mentorId)) {
            return false;
        }

        return (bool) (self::messagingEntitlement($menteeUserId, $mentorId)['active'] ?? false);
    }

    /**
     * CTA when paid messaging window is inactive (same copy for mentor + mentee).
     * Includes purchase prompt + admin WhatsApp contact option.
     *
     * @return array{message:string,admin_whatsapp:?string,admin_whatsapp_display:?string,admin_whatsapp_url:?string}
     */
    public static function messagingDisabledPayload(): array
    {
        $display = trim((string) config('session_chat.admin_whatsapp_display', '+91 73669 39888'));
        $phone = trim((string) config('session_chat.admin_whatsapp', '7366939888'));
        $digits = WhatsAppWebLink::digits($phone !== '' ? $phone : $display);
        $waUrl = WhatsAppWebLink::url(
            $digits,
            'Hi Admin, my mentorship messaging window has expired. Please help me renew messaging / purchase another session.'
        );

        $configured = config('session_chat.messaging_disabled_message');
        if (is_string($configured) && trim($configured) !== '') {
            $message = trim($configured);
        } else {
            $message = 'Messaging is disabled because more than one week has passed since your last session. '
                .'Please purchase another session to enable messaging again. '
                .'Or contact admin on WhatsApp: '.($display !== '' ? $display : '+91 73669 39888');
        }

        return [
            'message' => $message,
            'admin_whatsapp' => $digits,
            'admin_whatsapp_display' => $display !== '' ? $display : '+91 73669 39888',
            'admin_whatsapp_url' => $waUrl,
        ];
    }

    /**
     * Additive send-gate for a mentee↔mentor thread.
     * Pack grant renews the window via ledger timestamps (no extra write on grant).
     *
     * @return array{active:bool,reason:string,active_until:?string,window_start:?string,disabled_message:?string,admin_whatsapp:?string,admin_whatsapp_display:?string,admin_whatsapp_url:?string,override:?string}
     */
    public static function messagingEntitlement(int $menteeUserId, int $mentorId): array
    {
        $disabled = self::messagingDisabledPayload();
        $windowDays = max(1, (int) config('session_chat.messaging_window_days', 7));

        $emptyDisabled = [
            'disabled_message' => null,
            'admin_whatsapp' => null,
            'admin_whatsapp_display' => null,
            'admin_whatsapp_url' => null,
        ];

        if (self::isSupportMentor($mentorId)) {
            return array_merge([
                'active' => true,
                'reason' => 'support',
                'active_until' => null,
                'window_start' => null,
                'override' => null,
            ], $emptyDisabled);
        }

        $overrideRow = SessionChatMessagingOverride::query()
            ->where('mentee_user_id', $menteeUserId)
            ->where('mentor_id', $mentorId)
            ->first();
        $override = $overrideRow ? (string) $overrideRow->override : null;

        if ($override === SessionChatMessagingOverride::FORCE_OFF) {
            return [
                'active' => false,
                'reason' => 'admin_force_off',
                'active_until' => null,
                'window_start' => null,
                'disabled_message' => $disabled['message'],
                'admin_whatsapp' => $disabled['admin_whatsapp'],
                'admin_whatsapp_display' => $disabled['admin_whatsapp_display'],
                'admin_whatsapp_url' => $disabled['admin_whatsapp_url'],
                'override' => $override,
            ];
        }

        if ($override === SessionChatMessagingOverride::FORCE_ON) {
            return array_merge([
                'active' => true,
                'reason' => 'admin_force_on',
                'active_until' => null,
                'window_start' => null,
                'override' => $override,
            ], $emptyDisabled);
        }

        if (self::hasPlannedSession($menteeUserId, $mentorId)) {
            return array_merge([
                'active' => true,
                'reason' => 'planned_session',
                'active_until' => null,
                'window_start' => null,
                'override' => $override,
            ], $emptyDisabled);
        }

        if (!self::hasPaidMessagingRelationship($menteeUserId, $mentorId)) {
            return array_merge([
                'active' => true,
                'reason' => 'free_demo',
                'active_until' => null,
                'window_start' => null,
                'override' => $override,
            ], $emptyDisabled);
        }

        $windowStart = self::messagingWindowStart($menteeUserId, $mentorId);
        if ($windowStart === null) {
            // Ambiguous paid data without timestamps — prefer not locking out.
            return array_merge([
                'active' => true,
                'reason' => 'within_window',
                'active_until' => null,
                'window_start' => null,
                'override' => $override,
            ], $emptyDisabled);
        }

        $activeUntil = $windowStart->copy()->addDays($windowDays);
        if (Carbon::now()->lte($activeUntil)) {
            return array_merge([
                'active' => true,
                'reason' => 'within_window',
                'active_until' => $activeUntil->toIso8601String(),
                'window_start' => $windowStart->toIso8601String(),
                'override' => $override,
            ], $emptyDisabled);
        }

        return [
            'active' => false,
            'reason' => 'expired',
            'active_until' => $activeUntil->toIso8601String(),
            'window_start' => $windowStart->toIso8601String(),
            'disabled_message' => $disabled['message'],
            'admin_whatsapp' => $disabled['admin_whatsapp'],
            'admin_whatsapp_display' => $disabled['admin_whatsapp_display'],
            'admin_whatsapp_url' => $disabled['admin_whatsapp_url'],
            'override' => $override,
        ];
    }

    public static function hasPlannedSession(int $menteeUserId, int $mentorId): bool
    {
        return MentorBooking::query()
            ->where('mentee_user_id', $menteeUserId)
            ->where('mentor_id', $mentorId)
            ->whereIn('status', ['assigned', 'requested', 'confirmed', 'reschedule_requested'])
            ->exists();
    }

    public static function messagingWindowStart(int $menteeUserId, int $mentorId): ?Carbon
    {
        $candidates = [];

        $creditIds = MentorSessionCredit::query()
            ->where('mentee_user_id', $menteeUserId)
            ->where('mentor_id', $mentorId)
            ->pluck('id');
        if ($creditIds->isNotEmpty()) {
            $grantAt = MentorSessionCreditLedger::query()
                ->whereIn('credit_id', $creditIds)
                ->where('type', 'grant')
                ->max('created_at');
            if ($grantAt) {
                $candidates[] = Carbon::parse($grantAt);
            }
        }

        $completed = MentorBooking::query()
            ->where('mentee_user_id', $menteeUserId)
            ->where('mentor_id', $mentorId)
            ->where('status', 'completed')
            ->get(['completed_at', 'scheduled_at', 'preferred_date', 'created_at']);
        foreach ($completed as $booking) {
            $ts = $booking->completed_at
                ?? $booking->scheduled_at
                ?? $booking->preferred_date
                ?? $booking->created_at;
            if ($ts) {
                $candidates[] = Carbon::parse($ts);
            }
        }

        $paidBookings = MentorBooking::query()
            ->where('mentee_user_id', $menteeUserId)
            ->where('mentor_id', $mentorId)
            ->where('payment_status', 'paid')
            ->get(['amount', 'tax_amount', 'scheduled_at', 'preferred_date', 'created_at']);
        foreach ($paidBookings as $booking) {
            if (((float) $booking->amount + (float) $booking->tax_amount) <= 0) {
                continue;
            }
            $ts = $booking->scheduled_at ?? $booking->preferred_date ?? $booking->created_at;
            if ($ts) {
                $candidates[] = Carbon::parse($ts);
            }
        }

        $demoIds = self::menteeDemoIds($menteeUserId);
        if ($demoIds->isNotEmpty()) {
            $pivots = DB::table('demo_booking_mentors')
                ->whereIn('demo_booking_id', $demoIds)
                ->where('mentor_id', $mentorId)
                ->where('paid_session_done', 1)
                ->get(['updated_at', 'assigned_at', 'created_at', 'demo_booking_id']);
            foreach ($pivots as $pivot) {
                $ts = $pivot->updated_at ?? $pivot->assigned_at ?? $pivot->created_at;
                if ($ts) {
                    $candidates[] = Carbon::parse($ts);
                }
            }
        }

        if ($candidates === []) {
            return null;
        }

        return collect($candidates)->sortByDesc(fn (Carbon $c) => $c->timestamp)->first();
    }

    public static function entitlementLabel(array $entitlement): string
    {
        $reason = (string) ($entitlement['reason'] ?? '');
        return match ($reason) {
            'support' => 'Support (always on)',
            'admin_force_on' => 'Admin forced on',
            'admin_force_off' => 'Admin forced off',
            'planned_session' => 'Planned session',
            'within_window' => !empty($entitlement['active_until'])
                ? ('Active until '.Carbon::parse($entitlement['active_until'])->format('d M Y'))
                : 'Active',
            'free_demo' => 'Free demo',
            'expired' => 'Expired',
            default => $entitlement['active'] ?? false ? 'Active' : 'Disabled',
        };
    }

    public static function studentCanAccess(int $userId, int $mentorId): bool
    {
        if (self::isSupportMentor($mentorId)) {
            return $userId > 0;
        }

        if (MentorBooking::query()
            ->where('mentee_user_id', $userId)
            ->where('mentor_id', $mentorId)
            ->exists()) {
            return true;
        }

        $user = User::find($userId);
        $email = trim((string) ($user?->email ?? ''));
        $demoIds = DemoBooking::query()
            ->where(function ($q) use ($userId, $email) {
                $q->where('user_id', $userId);
                if ($email !== '') {
                    $q->orWhere('email', $email);
                }
            })
            ->pluck('id');
        if ($demoIds->isEmpty()) {
            return false;
        }

        return DB::table('demo_booking_mentors')
            ->whereIn('demo_booking_id', $demoIds)
            ->where('mentor_id', $mentorId)
            ->exists();
    }

    public static function menteeDemoIds(int $menteeUserId): \Illuminate\Support\Collection
    {
        $user = User::find($menteeUserId);
        $email = trim((string) ($user?->email ?? ''));

        return DemoBooking::query()
            ->where(function ($q) use ($menteeUserId, $email) {
                $q->where('user_id', $menteeUserId);
                if ($email !== '') {
                    $q->orWhere('email', $email);
                }
            })
            ->pluck('id');
    }

    public static function mentorCanAccess(int $mentorId, int $menteeUserId): bool
    {
        if (MentorBooking::query()
            ->where('mentor_id', $mentorId)
            ->where('mentee_user_id', $menteeUserId)
            ->exists()) {
            return true;
        }

        $demoIds = self::menteeDemoIds($menteeUserId);
        if ($demoIds->isEmpty()) {
            return false;
        }

        return DB::table('demo_booking_mentors')
            ->whereIn('demo_booking_id', $demoIds)
            ->where('mentor_id', $mentorId)
            ->exists();
    }

    public static function studentMessageCount(int $menteeUserId, int $mentorId): int
    {
        return SessionChatMessage::query()
            ->where('mentee_user_id', $menteeUserId)
            ->where('mentor_id', $mentorId)
            ->where('sender_role', 'mentee')
            ->count();
    }

    public static function quotaPayload(int $menteeUserId, int $mentorId): array
    {
        $entitlement = self::messagingEntitlement($menteeUserId, $mentorId);
        $used = self::studentMessageCount($menteeUserId, $mentorId);

        if (self::isSupportMentor($mentorId)) {
            return [
                'is_paid_unlimited' => true,
                'student_messages_used' => $used,
                'student_message_limit' => null,
                'student_can_send' => true,
                'mentor_can_send' => true,
                'messaging_active' => true,
                'messaging_reason' => 'support',
                'messaging_active_until' => null,
                'messaging_disabled_message' => null,
                'admin_whatsapp' => null,
                'admin_whatsapp_display' => null,
                'admin_whatsapp_url' => null,
                'is_support' => true,
            ];
        }

        if (!($entitlement['active'] ?? false)) {
            return [
                'is_paid_unlimited' => false,
                'student_messages_used' => $used,
                'student_message_limit' => self::FREE_STUDENT_LIMIT,
                'student_can_send' => false,
                'mentor_can_send' => false,
                'messaging_active' => false,
                'messaging_reason' => $entitlement['reason'] ?? 'expired',
                'messaging_active_until' => $entitlement['active_until'] ?? null,
                'messaging_disabled_message' => $entitlement['disabled_message'] ?? null,
                'admin_whatsapp' => $entitlement['admin_whatsapp'] ?? null,
                'admin_whatsapp_display' => $entitlement['admin_whatsapp_display'] ?? null,
                'admin_whatsapp_url' => $entitlement['admin_whatsapp_url'] ?? null,
                'is_support' => false,
            ];
        }

        $unlimited = self::hasPaidMessagingRelationship($menteeUserId, $mentorId);
        $limit = $unlimited ? null : self::FREE_STUDENT_LIMIT;

        return [
            'is_paid_unlimited' => $unlimited,
            'student_messages_used' => $used,
            'student_message_limit' => $limit,
            'student_can_send' => $unlimited || $used < self::FREE_STUDENT_LIMIT,
            'mentor_can_send' => true,
            'messaging_active' => true,
            'messaging_reason' => $entitlement['reason'] ?? 'within_window',
            'messaging_active_until' => $entitlement['active_until'] ?? null,
            'messaging_disabled_message' => null,
            'admin_whatsapp' => null,
            'admin_whatsapp_display' => null,
            'admin_whatsapp_url' => null,
            'is_support' => false,
        ];
    }

    public static function relatedIds(int $menteeUserId, int $mentorId): array
    {
        $demoIds = self::menteeDemoIds($menteeUserId);
        $demoId = $demoIds->isEmpty()
            ? null
            : DB::table('demo_booking_mentors')
                ->whereIn('demo_booking_id', $demoIds)
                ->where('mentor_id', $mentorId)
                ->orderByDesc('id')
                ->value('demo_booking_id');

        $bookingId = MentorBooking::query()
            ->where('mentee_user_id', $menteeUserId)
            ->where('mentor_id', $mentorId)
            ->orderByDesc('id')
            ->value('id');

        return [
            'demo_booking_id' => $demoId ? (int) $demoId : null,
            'mentor_booking_id' => $bookingId ? (int) $bookingId : null,
        ];
    }

    public static function formatMessage(SessionChatMessage $row): array
    {
        $payload = [
            'id' => $row->id,
            'sender_role' => $row->sender_role,
            'body' => $row->body ?? '',
            'created_at' => $row->created_at ? $row->created_at->toIso8601String() : null,
        ];

        if ($row->attachment_path) {
            $payload['attachment'] = self::formatAttachment($row);
        }

        return $payload;
    }

    public static function formatAttachment(SessionChatMessage $row): array
    {
        return [
            'original_name' => (string) $row->attachment_original_name,
            'mime_type' => (string) $row->attachment_mime,
            'size' => (int) $row->attachment_size,
            'download_url' => '/api/v1/session-chats/messages/'.$row->id.'/attachment',
        ];
    }

    public static function isDangerousAttachment(UploadedFile $file): bool
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: '');

        return $ext === '' || in_array($ext, self::BLOCKED_EXTENSIONS, true);
    }

    /**
     * @return array{path: string, original_name: string, mime: string, size: int}|null
     */
    public static function storeAttachment(UploadedFile $file): ?array
    {
        if (self::isDangerousAttachment($file)) {
            return null;
        }

        $path = $file->store('session-chat-attachments', 'local');
        if (!$path) {
            return null;
        }

        return [
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType() ?: 'application/octet-stream',
            'size' => (int) $file->getSize(),
        ];
    }

    public static function deleteAttachment(?string $path): void
    {
        if ($path && Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }
    }

    public static function emailBodyForMessage(SessionChatMessage $message): string
    {
        $body = trim((string) $message->body);
        $fileName = trim((string) $message->attachment_original_name);

        if ($body !== '' && $fileName !== '') {
            return $body."\n\nAttachment: ".$fileName;
        }
        if ($body !== '') {
            return $body;
        }
        if ($fileName !== '') {
            return 'Sent a file: '.$fileName;
        }

        return '';
    }

    public static function userCanAccessMessage(int $userId, SessionChatMessage $message): bool
    {
        $mentorProfile = self::mentorProfileForUser($userId);
        if ($mentorProfile && (int) $mentorProfile->id === (int) $message->mentor_id) {
            return self::mentorCanAccess((int) $message->mentor_id, (int) $message->mentee_user_id);
        }

        if ((int) $userId === (int) $message->mentee_user_id) {
            return self::studentCanAccess((int) $message->mentee_user_id, (int) $message->mentor_id);
        }

        return false;
    }

    public static function previewTextForMessage(?SessionChatMessage $message): string
    {
        if (!$message) {
            return '';
        }

        return self::emailBodyForMessage($message);
    }

    public static function otherPartySenderRole(int $viewerUserId, int $mentorId, int $menteeUserId): string
    {
        $mentorProfile = self::mentorProfileForUser($viewerUserId);
        if ($mentorProfile && (int) $mentorProfile->id === $mentorId) {
            return 'mentee';
        }

        return 'mentor';
    }

    public static function unreadCountForViewer(int $viewerUserId, int $mentorId, int $menteeUserId): int
    {
        $otherRole = self::otherPartySenderRole($viewerUserId, $mentorId, $menteeUserId);
        $lastReadId = SessionChatRead::query()
            ->where('user_id', $viewerUserId)
            ->where('mentor_id', $mentorId)
            ->where('mentee_user_id', $menteeUserId)
            ->value('last_read_message_id');

        $query = SessionChatMessage::query()
            ->where('mentee_user_id', $menteeUserId)
            ->where('mentor_id', $mentorId);

        // Students count mentor + admin messages as unread (ADMIN support thread).
        if ($otherRole === 'mentor') {
            $query->whereIn('sender_role', ['mentor', 'admin']);
        } else {
            $query->where('sender_role', $otherRole);
        }

        if ($lastReadId) {
            $query->where('id', '>', (int) $lastReadId);
        }

        return (int) $query->count();
    }

    public static function markThreadRead(int $viewerUserId, int $mentorId, int $menteeUserId): int
    {
        $latestId = SessionChatMessage::query()
            ->where('mentee_user_id', $menteeUserId)
            ->where('mentor_id', $mentorId)
            ->orderByDesc('id')
            ->value('id');

        $latestId = $latestId ? (int) $latestId : null;

        SessionChatRead::query()->updateOrCreate(
            [
                'user_id' => $viewerUserId,
                'mentor_id' => $mentorId,
                'mentee_user_id' => $menteeUserId,
            ],
            [
                'last_read_message_id' => $latestId,
            ]
        );

        return $latestId ?? 0;
    }

    public static function totalUnreadForViewer(int $viewerUserId, ?string $as = null): int
    {
        $as = $as === 'mentor' || $as === 'mentee' ? $as : null;
        $mentorProfile = self::mentorProfileForUser($viewerUserId);

        if ($as === 'mentor' || ($as === null && $mentorProfile)) {
            if (!$mentorProfile) {
                return 0;
            }
            $threads = self::threadsForMentor((int) $mentorProfile->id);
            $sum = (int) array_sum(array_column($threads, 'unread_count'));

            // Mentors also have an ADMIN/support thread (as mentee of the support mentor).
            $supportId = self::ensureSupportMentorId();
            $sum += self::unreadCountForViewer($viewerUserId, $supportId, $viewerUserId);

            return $sum;
        }

        $threads = self::threadsForMentee($viewerUserId);

        return (int) array_sum(array_column($threads, 'unread_count'));
    }

    public static function studentFirstName(?User $user, ?DemoBooking $demo = null): string
    {
        if ($user && trim((string) $user->f_name) !== '') {
            return self::firstName($user->f_name);
        }
        if ($demo) {
            return self::firstName($demo->name);
        }

        return 'Student';
    }

    public static function mentorProfileForUser(int $userId): ?Mentor
    {
        return Mentor::where('user_id', $userId)->first();
    }

    public static function assignmentsForMentor(Mentor $mentor): array
    {
        $demoIds = DB::table('demo_booking_mentors')
            ->where('mentor_id', $mentor->id)
            ->orderByDesc('id')
            ->pluck('demo_booking_id');

        if ($demoIds->isEmpty()) {
            return [];
        }

        $demos = DemoBooking::query()
            ->with('user')
            ->whereIn('id', $demoIds)
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->keyBy('id');

        $pivots = DB::table('demo_booking_mentors')
            ->where('mentor_id', $mentor->id)
            ->whereIn('demo_booking_id', $demoIds)
            ->get()
            ->keyBy('demo_booking_id');

        $out = [];
        foreach ($demoIds as $demoId) {
            $demo = $demos->get($demoId);
            if (!$demo) {
                continue;
            }
            $pivot = $pivots->get($demoId);
            $user = $demo->user;
            $menteeUserId = $user ? (int) $user->id : ((int) $demo->user_id ?: null);
            if (!$menteeUserId) {
                $email = trim((string) $demo->email);
                if ($email !== '') {
                    $menteeUserId = User::where('email', $email)->value('id');
                    $menteeUserId = $menteeUserId ? (int) $menteeUserId : null;
                    if ($menteeUserId) {
                        $user = User::find($menteeUserId);
                    }
                }
            }
            $chatEnabled = $menteeUserId !== null && $menteeUserId > 0;
            $quota = $chatEnabled
                ? self::quotaPayload($menteeUserId, (int) $mentor->id)
                : [
                    'is_paid_unlimited' => false,
                    'student_messages_used' => 0,
                    'student_message_limit' => self::FREE_STUDENT_LIMIT,
                    'student_can_send' => false,
                    'mentor_can_send' => false,
                    'messaging_active' => false,
                    'messaging_reason' => null,
                    'messaging_active_until' => null,
                    'messaging_disabled_message' => null,
                    'admin_whatsapp' => null,
                    'admin_whatsapp_display' => null,
                    'admin_whatsapp_url' => null,
                ];

            $out[] = array_merge([
                'demo_booking_id' => (int) $demo->id,
                'mentor_id' => (int) $mentor->id,
                'booking_ref' => $demo->booking_ref,
                'category_label' => $demo->category_label ?: $demo->demoProgramLabel(),
                'student_first_name' => self::studentFirstName($user, $demo),
                'mentee_user_id' => $menteeUserId,
                'chat_enabled' => $chatEnabled,
                'assigned_at' => $pivot->assigned_at ?? null,
                'paid_session_done' => (bool) ($pivot->paid_session_done ?? false),
            ], $quota);
        }

        return $out;
    }

    public static function threadSummaryForMenteeMentor(int $menteeUserId, int $mentorId, ?Mentor $mentor = null): array
    {
        $mentor = $mentor ?: Mentor::query()->find($mentorId);
        $isSupport = self::isSupportMentor($mentorId);
        $last = SessionChatMessage::query()
            ->where('mentee_user_id', $menteeUserId)
            ->where('mentor_id', $mentorId)
            ->orderByDesc('id')
            ->first();
        $count = SessionChatMessage::query()
            ->where('mentee_user_id', $menteeUserId)
            ->where('mentor_id', $mentorId)
            ->count();

        $formatted = $last ? self::formatMessage($last) : null;
        if ($formatted !== null) {
            $formatted['preview'] = self::previewTextForMessage($last);
        }

        $quota = self::quotaPayload($menteeUserId, $mentorId);

        return array_merge([
            'mentor_id' => $mentorId,
            'display_name' => $isSupport
                ? self::supportMentorDisplayName()
                : ($mentor?->display_name ?? ('Mentor #'.$mentorId)),
            'username' => $mentor?->username,
            'headline' => $isSupport ? 'MentorKhoj support' : $mentor?->headline,
            'message_count' => $count,
            'last_message' => $formatted,
            'chat_enabled' => true,
            'unread_count' => self::unreadCountForViewer($menteeUserId, $mentorId, $menteeUserId),
        ], $quota);
    }

    public static function threadsForMentee(int $menteeUserId): array
    {
        $supportMentorId = self::ensureSupportMentorId();

        $mentorIds = MentorBooking::query()
            ->where('mentee_user_id', $menteeUserId)
            ->distinct()
            ->pluck('mentor_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values();

        $demoIds = self::menteeDemoIds($menteeUserId);
        if ($demoIds->isNotEmpty()) {
            $fromDemo = DB::table('demo_booking_mentors')
                ->whereIn('demo_booking_id', $demoIds)
                ->pluck('mentor_id')
                ->map(fn ($id) => (int) $id);
            $mentorIds = $mentorIds->merge($fromDemo)->unique()->values();
        }

        // Always include ADMIN/support thread for every student.
        $mentorIds = $mentorIds
            ->reject(fn ($id) => (int) $id === $supportMentorId)
            ->values()
            ->prepend($supportMentorId)
            ->unique()
            ->values();

        $mentors = Mentor::query()
            ->whereIn('id', $mentorIds)
            ->get()
            ->keyBy('id');

        $threads = [];
        foreach ($mentorIds as $mentorId) {
            if (!self::studentCanAccess($menteeUserId, $mentorId)) {
                continue;
            }
            $threads[] = self::threadSummaryForMenteeMentor(
                $menteeUserId,
                $mentorId,
                $mentors->get($mentorId)
            );
        }

        usort($threads, function ($a, $b) {
            // Pin support/ADMIN thread near top when it has unread or any activity.
            $aSupport = !empty($a['is_support']);
            $bSupport = !empty($b['is_support']);
            $aUnread = (int) ($a['unread_count'] ?? 0);
            $bUnread = (int) ($b['unread_count'] ?? 0);
            if ($aSupport && $aUnread > 0 && !($bSupport && $bUnread > 0)) {
                return -1;
            }
            if ($bSupport && $bUnread > 0 && !($aSupport && $aUnread > 0)) {
                return 1;
            }
            $ta = $a['last_message']['created_at'] ?? '';
            $tb = $b['last_message']['created_at'] ?? '';
            if ($ta === '' && $aSupport && $tb !== '') {
                return 1;
            }
            if ($tb === '' && $bSupport && $ta !== '') {
                return -1;
            }

            return strcmp($tb, $ta);
        });

        return $threads;
    }

    public static function threadsForMentor(int $mentorId): array
    {
        $mentorUserId = (int) (Mentor::query()->where('id', $mentorId)->value('user_id') ?? 0);

        $menteeIds = MentorBooking::query()
            ->where('mentor_id', $mentorId)
            ->whereNotNull('mentee_user_id')
            ->distinct()
            ->pluck('mentee_user_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values();

        $demoIds = DB::table('demo_booking_mentors')
            ->where('mentor_id', $mentorId)
            ->pluck('demo_booking_id');

        if ($demoIds->isNotEmpty()) {
            $demos = DemoBooking::query()
                ->with('user')
                ->whereIn('id', $demoIds)
                ->get();

            foreach ($demos as $demo) {
                $user = $demo->user;
                $menteeUserId = $user ? (int) $user->id : ((int) $demo->user_id ?: null);
                if (!$menteeUserId) {
                    $email = trim((string) $demo->email);
                    if ($email !== '') {
                        $found = User::where('email', $email)->value('id');
                        $menteeUserId = $found ? (int) $found : null;
                    }
                }
                if ($menteeUserId && $menteeUserId > 0) {
                    $menteeIds->push($menteeUserId);
                }
            }
            $menteeIds = $menteeIds->unique()->values();
        }

        $users = User::query()
            ->whereIn('id', $menteeIds)
            ->get()
            ->keyBy('id');

        $threads = [];
        foreach ($menteeIds as $menteeUserId) {
            if (!self::mentorCanAccess($mentorId, $menteeUserId)) {
                continue;
            }

            $user = $users->get($menteeUserId);
            $displayName = self::studentFirstName($user);

            $latestBooking = MentorBooking::query()
                ->with('service')
                ->where('mentor_id', $mentorId)
                ->where('mentee_user_id', $menteeUserId)
                ->orderByDesc('id')
                ->first();

            $categoryLabel = $latestBooking?->service?->title;
            if (!$categoryLabel && $demoIds->isNotEmpty()) {
                $menteeDemoIds = self::menteeDemoIds($menteeUserId);
                $sharedDemoIds = $menteeDemoIds->intersect($demoIds);
                if ($sharedDemoIds->isNotEmpty()) {
                    $demo = DemoBooking::query()
                        ->whereIn('id', $sharedDemoIds)
                        ->orderByDesc('id')
                        ->first();
                    if ($demo) {
                        $categoryLabel = $demo->category_label ?: $demo->demoProgramLabel();
                    }
                }
            }

            $last = SessionChatMessage::query()
                ->where('mentee_user_id', $menteeUserId)
                ->where('mentor_id', $mentorId)
                ->orderByDesc('id')
                ->first();
            $count = SessionChatMessage::query()
                ->where('mentee_user_id', $menteeUserId)
                ->where('mentor_id', $mentorId)
                ->count();

            $formatted = $last ? self::formatMessage($last) : null;
            if ($formatted !== null) {
                $formatted['preview'] = self::previewTextForMessage($last);
            }

            $quota = self::quotaPayload($menteeUserId, $mentorId);

            $threads[] = array_merge([
                'mentee_user_id' => $menteeUserId,
                'display_name' => $displayName,
                'category_label' => $categoryLabel,
                'message_count' => $count,
                'last_message' => $formatted,
                'chat_enabled' => true,
                'unread_count' => $mentorUserId > 0
                    ? self::unreadCountForViewer($mentorUserId, $mentorId, $menteeUserId)
                    : 0,
            ], $quota);
        }

        usort($threads, function ($a, $b) {
            $ta = $a['last_message']['created_at'] ?? '';
            $tb = $b['last_message']['created_at'] ?? '';

            return strcmp($tb, $ta);
        });

        return $threads;
    }

}
