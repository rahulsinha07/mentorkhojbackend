<?php

namespace App\CentralLogics;

use App\Model\DemoBooking;
use App\Model\Mentor\Mentor;
use App\Model\Mentor\MentorBooking;
use App\Model\SessionChatMessage;
use App\User;
use Illuminate\Support\Facades\DB;

class SessionChatLogic
{
    public const FREE_STUDENT_LIMIT = 5;
    public const PII_ERROR = 'cannot send this as it has PII';

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

    public static function isPaidUnlimited(int $menteeUserId, int $mentorId): bool
    {
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

    public static function studentCanAccess(int $userId, int $mentorId): bool
    {
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
        $unlimited = self::isPaidUnlimited($menteeUserId, $mentorId);
        $used = self::studentMessageCount($menteeUserId, $mentorId);
        $limit = $unlimited ? null : self::FREE_STUDENT_LIMIT;

        return [
            'is_paid_unlimited' => $unlimited,
            'student_messages_used' => $used,
            'student_message_limit' => $limit,
            'student_can_send' => $unlimited || $used < self::FREE_STUDENT_LIMIT,
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
        return [
            'id' => $row->id,
            'sender_role' => $row->sender_role,
            'body' => $row->body,
            'created_at' => $row->created_at ? $row->created_at->toIso8601String() : null,
        ];
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
}
