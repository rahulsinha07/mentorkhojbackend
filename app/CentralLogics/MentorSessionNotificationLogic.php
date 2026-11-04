<?php

namespace App\CentralLogics;

use App\Model\Mentor\MentorSessionNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MentorSessionNotificationLogic
{
    public static function shouldSend(MentorSessionNotification $notification): bool
    {
        return in_array((string) $notification->status, ['pending', 'sending'], true)
            && !$notification->sent_at;
    }

    public static function markSent(MentorSessionNotification $notification): void
    {
        $notification->update([
            'status' => 'sent',
            'sent_at' => now(),
            'error_message' => null,
        ]);
    }

    public static function markFailed(MentorSessionNotification $notification, string $error): void
    {
        $notification->update([
            'status' => 'failed',
            'error_message' => mb_substr($error, 0, 2000),
        ]);
    }

    /**
     * Idempotently claim a notification slot. Returns null when already sent or in-flight elsewhere.
     */
    public static function claimNotification(
        ?int $bookingId,
        ?int $userId,
        string $type,
        ?Carbon $scheduledFor = null
    ): ?MentorSessionNotification {
        return DB::transaction(function () use ($bookingId, $userId, $type, $scheduledFor) {
            $query = MentorSessionNotification::query()
                ->where('notification_type', $type)
                ->lockForUpdate();

            if ($bookingId !== null) {
                $query->where('mentor_booking_id', $bookingId);
            }
            if ($userId !== null) {
                $query->where('recipient_user_id', $userId);
            }
            if ($scheduledFor !== null) {
                $query->where('scheduled_for', $scheduledFor);
            }

            $existing = $query->first();
            if ($existing) {
                if ($existing->status === 'sent' || $existing->sent_at) {
                    return null;
                }
                if ($existing->status === 'sending') {
                    return null;
                }

                $existing->update(['status' => 'sending']);

                return $existing->fresh();
            }

            return MentorSessionNotification::create([
                'mentor_booking_id' => $bookingId,
                'recipient_user_id' => $userId,
                'notification_type' => $type,
                'scheduled_for' => $scheduledFor,
                'status' => 'sending',
            ]);
        });
    }
}
