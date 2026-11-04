<?php

namespace App\CentralLogics;

use App\Model\Mentor\MentorBooking;
use App\Model\UserNotification;
use App\User;

class UserNotificationLogic
{
    public static function create(
        int $userId,
        string $type,
        string $title,
        ?string $body = null,
        ?string $link = null,
        ?int $bookingId = null,
        array $pushExtra = []
    ): void {
        UserNotification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'link' => $link,
            'mentor_booking_id' => $bookingId,
        ]);

        // Phone push is extra. The website response and email must not wait on Firebase.
        $pushTitle = $title;
        $pushBody = (string) ($body ?? '');
        $pushLink = (string) ($link ?? '');
        $pushBookingId = $bookingId ? (string) $bookingId : '';
        try {
            app()->terminating(function () use ($userId, $type, $pushTitle, $pushBody, $pushLink, $pushBookingId, $pushExtra) {
                try {
                    $user = User::query()->find($userId);
                    $token = $user ? (string) ($user->cm_firebase_token ?? '') : '';
                    Helpers::send_push_notif_to_device($token, array_merge([
                        'title' => $pushTitle,
                        'description' => $pushBody,
                        'type' => $type,
                        'order_id' => $pushBookingId,
                        'image' => '',
                        'link' => $pushLink,
                        'mentor_booking_id' => $pushBookingId,
                    ], $pushExtra));
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('User notification push skipped: '.$e->getMessage());
                }
            });
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('User notification push skipped: '.$e->getMessage());
        }
    }

    public static function sessionLink(MentorBooking $booking, bool $forMentor): string
    {
        $siteUrl = rtrim((string) config('app.mentorkhoj_site_url', 'https://www.mentorkhoj.com'), '/');

        return $forMentor
            ? "{$siteUrl}/mentor/dashboard/bookings/{$booking->id}"
            : "{$siteUrl}/account/sessions/{$booking->id}";
    }

    public static function notifyScheduled(MentorBooking $booking): void
    {
        $booking->loadMissing(['mentor.user', 'mentee']);
        $label = self::sessionLabel($booking);

        if ($booking->mentee_user_id) {
            self::create(
                (int) $booking->mentee_user_id,
                'session_scheduled',
                'Session scheduled',
                $label,
                self::sessionLink($booking, false),
                (int) $booking->id
            );
        }

        $mentorUserId = (int) ($booking->mentor?->user_id ?? 0);
        if ($mentorUserId > 0) {
            self::create(
                $mentorUserId,
                'session_scheduled',
                'Session scheduled',
                $label,
                self::sessionLink($booking, true),
                (int) $booking->id
            );
        }
    }

    public static function notifyRescheduleRequested(MentorBooking $booking): void
    {
        $booking->loadMissing(['mentor.user', 'mentee']);
        $mentorUserId = (int) ($booking->mentor?->user_id ?? 0);
        if ($mentorUserId <= 0) {
            return;
        }

        self::create(
            $mentorUserId,
            'reschedule_requested',
            'Reschedule request',
            self::sessionLabel($booking).' requested a new time.',
            self::sessionLink($booking, true),
            (int) $booking->id
        );
    }

    public static function notifyRescheduleApproved(MentorBooking $booking): void
    {
        $booking->loadMissing(['mentee']);
        if (!$booking->mentee_user_id) {
            return;
        }

        self::create(
            (int) $booking->mentee_user_id,
            'reschedule_approved',
            'Reschedule approved',
            self::sessionLabel($booking).' was approved.',
            self::sessionLink($booking, false),
            (int) $booking->id
        );
    }

    public static function notifyCancelled(MentorBooking $booking, bool $slotRestored): void
    {
        $booking->loadMissing(['mentor.user', 'mentee']);
        $body = $slotRestored
            ? 'Your session slot was returned to pending schedule.'
            : 'The session was cancelled.';

        if ($booking->mentee_user_id) {
            self::create(
                (int) $booking->mentee_user_id,
                'session_cancelled',
                'Session cancelled',
                $body,
                self::sessionLink($booking, false),
                (int) $booking->id
            );
        }

        $mentorUserId = (int) ($booking->mentor?->user_id ?? 0);
        if ($mentorUserId > 0) {
            self::create(
                $mentorUserId,
                'session_cancelled',
                'Session cancelled',
                $body,
                self::sessionLink($booking, true),
                (int) $booking->id
            );
        }
    }

    public static function notifyFeedbackRequested(MentorBooking $booking): void
    {
        $booking->loadMissing(['mentor']);
        if (!$booking->mentee_user_id) {
            return;
        }

        $mentorName = trim((string) ($booking->mentor?->display_name ?? ''));

        self::create(
            (int) $booking->mentee_user_id,
            'feedback_requested',
            'Feedback requested',
            ($mentorName !== '' ? $mentorName : 'Your mentor').' would like your feedback on '.self::sessionLabel($booking).'.',
            self::sessionLink($booking, false).'?feedback=1',
            (int) $booking->id
        );
    }

    public static function notifyNoShow(MentorBooking $booking): void
    {
        $booking->loadMissing(['mentee']);
        if (!$booking->mentee_user_id) {
            return;
        }

        self::create(
            (int) $booking->mentee_user_id,
            'session_no_show',
            'Session marked no-show',
            self::sessionLabel($booking).' was marked as no-show.',
            self::sessionLink($booking, false),
            (int) $booking->id
        );
    }

    private static function sessionLabel(MentorBooking $booking): string
    {
        if ($booking->session_number && $booking->total_sessions) {
            return "Session {$booking->session_number} of {$booking->total_sessions}";
        }

        return 'Your mentor session';
    }
}
