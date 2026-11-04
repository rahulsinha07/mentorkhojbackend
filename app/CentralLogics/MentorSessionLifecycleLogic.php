<?php

namespace App\CentralLogics;

use App\Model\Mentor\Mentor;
use App\Model\Mentor\MentorBooking;
use App\Model\Mentor\MentorBookingEvent;
use App\Model\Mentor\MentorService;
use App\Model\Mentor\MentorSessionCredit;
use App\Services\ZohoMeetingService;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class MentorSessionLifecycleLogic
{
    public static function appTimezone(): string
    {
        $tz = (string) (Helpers::get_business_settings('timezone') ?? '');

        return $tz !== '' ? $tz : 'Asia/Kolkata';
    }

    /**
     * Ensure one assigned booking row exists per session slot in the pack (1..credits_total).
     *
     * @return Collection<int, MentorBooking>
     */
    public static function ensurePackSessionRows(MentorSessionCredit $credit): Collection
    {
        $credit->loadMissing(['mentor', 'mentee']);
        $mentor = $credit->mentor;
        if (!$mentor) {
            throw new \RuntimeException('Mentor not found for session pack.');
        }

        $total = (int) $credit->credits_total;
        if ($total < 1) {
            return collect();
        }

        return DB::transaction(function () use ($credit, $mentor, $total) {
            $existing = MentorBooking::query()
                ->where('session_credit_id', $credit->id)
                ->whereIn('booking_source', ['credit', 'session'])
                ->whereNotIn('status', ['cancelled', 'refunded'])
                ->lockForUpdate()
                ->get();

            $unnumbered = $existing->filter(fn (MentorBooking $b) => $b->session_number === null)->sortBy('id')->values();
            $usedNumbers = $existing->pluck('session_number')->filter()->map(fn ($n) => (int) $n)->all();
            $nextNum = 1;
            foreach ($unnumbered as $row) {
                while (in_array($nextNum, $usedNumbers, true)) {
                    $nextNum++;
                }
                if ($nextNum > $total) {
                    break;
                }
                $row->session_number = $nextNum;
                $row->total_sessions = $total;
                $row->save();
                $usedNumbers[] = $nextNum;
                $nextNum++;
            }

            $existing = MentorBooking::query()
                ->where('session_credit_id', $credit->id)
                ->whereIn('booking_source', ['credit', 'session'])
                ->whereNotIn('status', ['cancelled', 'refunded'])
                ->get();

            $byNumber = $existing->filter(fn (MentorBooking $b) => $b->session_number !== null)
                ->keyBy('session_number');

            if ($existing->count() >= $total) {
                return $existing->values();
            }

            $service = SessionCreditLogic::resolveService($mentor);
            $amounts = SessionCreditLogic::computeSessionAmounts($credit);
            $created = collect();

            for ($n = 1; $n <= $total; $n++) {
                if ($byNumber->has($n)) {
                    continue;
                }

                $booking = MentorBooking::create([
                    'mentor_id' => $mentor->id,
                    'mentor_service_id' => $service->id,
                    'mentee_user_id' => $credit->mentee_user_id,
                    'session_credit_id' => $credit->id,
                    'session_number' => $n,
                    'total_sessions' => $total,
                    'duration_minutes' => (int) ($service->duration_minutes ?: 30),
                    'timezone' => self::appTimezone(),
                    'mentee_note' => $credit->notes,
                    'status' => 'assigned',
                    'amount' => $amounts['gross'],
                    'tax_amount' => 0,
                    'platform_fee' => $amounts['platform_fee'],
                    'mentor_net' => $amounts['mentor_net'],
                    'payment_status' => 'paid',
                    'booking_source' => 'session',
                ]);

                self::recordEvent($booking, null, 'session_assigned', [
                    'session_number' => $n,
                    'total_sessions' => $total,
                ]);

                $created->push($booking);
            }

            return $existing->merge($created)->values();
        });
    }

    public static function scheduleSession(MentorBooking $booking, array $input, ?User $actor = null): MentorBooking
    {
        if (!in_array((string) $booking->status, ['assigned', 'requested', 'confirmed', 'reschedule_requested'], true)) {
            throw new \RuntimeException('This session cannot be scheduled in its current state.');
        }

        $when = self::parseScheduleInput($input);
        if ($when->lt(now()->subMinute())) {
            throw new \InvalidArgumentException('Session date/time must be now or in the future.');
        }

        return DB::transaction(function () use ($booking, $input, $when, $actor) {
            $booking = MentorBooking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            self::applyScheduleFields($booking, $when, $input);
            self::applyZohoMeetingLink($booking, $input);
            $booking->status = 'confirmed';
            self::resetReminderFlags($booking);
            $booking->reschedule_reason = null;
            $booking->reschedule_requested_by_user_id = null;
            $booking->reschedule_requested_at = null;
            $booking->reschedule_proposed_date = null;
            $booking->reschedule_proposed_time = null;
            $booking->save();

            self::recordEvent($booking, $actor?->id, 'session_scheduled', [
                'scheduled_at' => $booking->scheduled_at?->toIso8601String(),
                'session_type' => $booking->session_type,
            ]);

            MentorBookingMailLogic::sendScheduleConfirmedNotify($booking->fresh(['mentor.user', 'service', 'mentee']), true);
            UserNotificationLogic::notifyScheduled($booking->fresh(['mentor.user', 'service', 'mentee']));

            return $booking->fresh(['service', 'mentor', 'mentee']);
        });
    }

    public static function requestReschedule(MentorBooking $booking, User $actor, array $input): MentorBooking
    {
        if ((int) $booking->mentee_user_id !== (int) $actor->id) {
            throw new \RuntimeException('Only the mentee can request a reschedule.');
        }
        if ((string) $booking->status !== 'confirmed') {
            throw new \RuntimeException('Only confirmed sessions can request a reschedule.');
        }

        $when = self::parseScheduleInput($input);
        if ($when->lt(now()->subMinute())) {
            throw new \InvalidArgumentException('Proposed date/time must be now or in the future.');
        }

        $booking->status = 'reschedule_requested';
        $booking->reschedule_requested_by_user_id = $actor->id;
        $booking->reschedule_requested_at = now();
        $booking->reschedule_proposed_date = $when->toDateString();
        $booking->reschedule_proposed_time = $when->format('H:i:s');
        if (!empty($input['reason'])) {
            $booking->reschedule_reason = (string) $input['reason'];
        }
        $booking->save();

        self::recordEvent($booking, $actor->id, 'reschedule_requested', [
            'proposed_date' => $booking->reschedule_proposed_date,
            'proposed_time' => $booking->reschedule_proposed_time,
            'reason' => $booking->reschedule_reason,
        ]);

        MentorBookingMailLogic::sendRescheduleRequestedToMentor($booking->fresh(['mentor.user', 'service', 'mentee']));
        UserNotificationLogic::notifyRescheduleRequested($booking->fresh(['mentor.user', 'service', 'mentee']));

        return $booking->fresh(['service', 'mentor', 'mentee']);
    }

    public static function approveReschedule(MentorBooking $booking, User $actor): MentorBooking
    {
        if ((string) $booking->status !== 'reschedule_requested') {
            throw new \RuntimeException('No pending reschedule request to approve.');
        }

        if (!$booking->reschedule_proposed_date || !$booking->reschedule_proposed_time) {
            throw new \RuntimeException('Reschedule proposal is missing date or time.');
        }

        $time = (string) $booking->reschedule_proposed_time;
        if (strlen($time) === 5) {
            $time .= ':00';
        }
        $when = Carbon::parse($booking->reschedule_proposed_date->format('Y-m-d').' '.$time, self::appTimezone());

        return DB::transaction(function () use ($booking, $actor, $when) {
            $booking = MentorBooking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            self::applyScheduleFields($booking, $when, []);
            self::applyZohoMeetingLink($booking, []);
            $booking->status = 'confirmed';
            self::resetReminderFlags($booking);
            $booking->reschedule_reason = null;
            $booking->reschedule_requested_by_user_id = null;
            $booking->reschedule_requested_at = null;
            $booking->reschedule_proposed_date = null;
            $booking->reschedule_proposed_time = null;
            $booking->save();

            self::recordEvent($booking, $actor->id, 'reschedule_approved', [
                'scheduled_at' => $booking->scheduled_at?->toIso8601String(),
            ]);

            MentorBookingMailLogic::sendRescheduleApproved($booking->fresh(['mentor.user', 'service', 'mentee']));
            UserNotificationLogic::notifyRescheduleApproved($booking->fresh(['mentor.user', 'service', 'mentee']));

            return $booking->fresh(['service', 'mentor', 'mentee']);
        });
    }

    public static function rejectReschedule(MentorBooking $booking, User $actor, ?string $reason = null): MentorBooking
    {
        if ((string) $booking->status !== 'reschedule_requested') {
            throw new \RuntimeException('No pending reschedule request to reject.');
        }

        $booking->status = 'confirmed';
        $booking->reschedule_proposed_date = null;
        $booking->reschedule_proposed_time = null;
        $booking->reschedule_requested_by_user_id = null;
        $booking->reschedule_requested_at = null;
        if ($reason) {
            $booking->reschedule_reason = $reason;
        }
        $booking->save();

        self::recordEvent($booking, $actor->id, 'reschedule_rejected', [
            'reason' => $reason,
        ]);

        return $booking->fresh(['service', 'mentor', 'mentee']);
    }

    public static function rescheduleDirect(MentorBooking $booking, User $actor, array $input): MentorBooking
    {
        if (!in_array((string) $booking->status, ['confirmed', 'reschedule_requested', 'assigned'], true)) {
            throw new \RuntimeException('This session cannot be rescheduled in its current state.');
        }

        $when = self::parseScheduleInput($input);
        if ($when->lt(now()->subMinute())) {
            throw new \InvalidArgumentException('Session date/time must be now or in the future.');
        }

        return DB::transaction(function () use ($booking, $actor, $input, $when) {
            $booking = MentorBooking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            self::applyScheduleFields($booking, $when, $input);
            self::applyZohoMeetingLink($booking, $input);
            $booking->status = 'confirmed';
            self::resetReminderFlags($booking);
            $booking->reschedule_reason = null;
            $booking->reschedule_requested_by_user_id = null;
            $booking->reschedule_requested_at = null;
            $booking->reschedule_proposed_date = null;
            $booking->reschedule_proposed_time = null;
            $booking->save();

            self::recordEvent($booking, $actor->id, 'session_rescheduled', [
                'scheduled_at' => $booking->scheduled_at?->toIso8601String(),
            ]);

            MentorBookingMailLogic::sendScheduleConfirmedNotify($booking->fresh(['mentor.user', 'service', 'mentee']), true);

            return $booking->fresh(['service', 'mentor', 'mentee']);
        });
    }

    public static function cancelSession(MentorBooking $booking, User $actor, string $reason): MentorBooking
    {
        if (!SessionCreditLogic::canCancel($booking)) {
            throw new \RuntimeException('This session cannot be cancelled in its current state.');
        }

        return DB::transaction(function () use ($booking, $actor, $reason) {
            $booking = MentorBooking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if (!SessionCreditLogic::canCancel($booking)) {
                throw new \RuntimeException('This session cannot be cancelled in its current state.');
            }

            $isPack = SessionCreditLogic::isSessionPackBooking($booking);
            $hadConsume = SessionCreditLogic::hadConsumedCredit($booking);
            if ($hadConsume) {
                SessionCreditLogic::restoreOnCancel($booking);
            }

            if (!empty($booking->zoho_meeting_id)) {
                app(ZohoMeetingService::class)->deleteMeeting($booking->zoho_meeting_id);
                $booking->zoho_meeting_id = null;
                $booking->meeting_link_source = null;
            }

            $slotRestored = false;
            if ($isPack && !$hadConsume) {
                $slotRestored = true;
                $booking->status = 'assigned';
                $booking->preferred_date = null;
                $booking->preferred_time = null;
                $booking->scheduled_at = null;
                $booking->meeting_url = null;
                $booking->session_type = null;
                $booking->cancellation_reason = null;
                $booking->cancelled_by_user_id = null;
                $booking->cancelled_at = null;
            } else {
                $booking->status = 'cancelled';
                $booking->cancellation_reason = $reason;
                if (Schema::hasColumn('mentor_bookings', 'cancelled_by_user_id')) {
                    $booking->cancelled_by_user_id = $actor->id;
                }
                if (Schema::hasColumn('mentor_bookings', 'cancelled_at')) {
                    $booking->cancelled_at = now();
                }
            }

            self::resetReminderFlags($booking);
            $booking->reschedule_reason = null;
            $booking->reschedule_requested_by_user_id = null;
            $booking->reschedule_requested_at = null;
            $booking->reschedule_proposed_date = null;
            $booking->reschedule_proposed_time = null;
            $booking->save();

            self::recordEvent($booking, $actor->id, 'session_cancelled', [
                'reason' => $reason,
                'slot_restored' => $slotRestored,
            ]);

            $fresh = $booking->fresh(['service', 'mentor', 'mentee']);
            MentorBookingMailLogic::sendSessionCancelled($fresh, $reason, $slotRestored);
            UserNotificationLogic::notifyCancelled($fresh, $slotRestored);

            return $fresh;
        });
    }

    public static function markNoShow(MentorBooking $booking, ?User $actor = null, ?string $reason = null): MentorBooking
    {
        if (!SessionCreditLogic::canMarkNoShow($booking)) {
            throw new \RuntimeException('This session cannot be marked as no-show yet.');
        }

        $booking->status = 'no_show';
        if ($reason) {
            $booking->cancellation_reason = $reason;
        }
        $booking->save();

        self::recordEvent($booking, $actor?->id, 'session_no_show', [
            'reason' => $reason,
        ]);

        $fresh = $booking->fresh(['service', 'mentor', 'mentee']);
        UserNotificationLogic::notifyNoShow($fresh);

        return $fresh;
    }

    public static function recordEvent(MentorBooking $booking, ?int $actorId, string $type, array $payload = []): void
    {
        MentorBookingEvent::create([
            'mentor_booking_id' => $booking->id,
            'actor_user_id' => $actorId,
            'event_type' => $type,
            'payload' => $payload,
            'created_at' => now(),
        ]);
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'assigned' => 'Awaiting schedule',
            'requested' => 'Requested',
            'confirmed' => 'Confirmed',
            'reschedule_requested' => 'Reschedule requested',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'no_show' => 'No show',
            'refunded' => 'Refunded',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    public static function applyScheduleFields(MentorBooking $booking, Carbon $when, array $input): void
    {
        $tz = (string) ($input['timezone'] ?? self::appTimezone());

        $booking->preferred_date = $when->toDateString();
        $booking->preferred_time = $when->format('H:i:s');
        $booking->scheduled_at = $when->copy()->timezone('UTC');
        $booking->timezone = $tz;

        if (!empty($input['session_type'])) {
            $booking->session_type = (string) $input['session_type'];
        }
        if (array_key_exists('meeting_url', $input)) {
            $booking->meeting_url = $input['meeting_url'] ? (string) $input['meeting_url'] : null;
        }
        if (array_key_exists('mentor_notes', $input)) {
            $booking->mentor_notes = $input['mentor_notes'] !== null ? (string) $input['mentor_notes'] : null;
        }
        if (array_key_exists('mentee_notes', $input)) {
            $booking->mentee_notes = $input['mentee_notes'] !== null ? (string) $input['mentee_notes'] : null;
        }
        if (!empty($input['duration_minutes'])) {
            $booking->duration_minutes = (int) $input['duration_minutes'];
        } elseif ($booking->service && !$booking->duration_minutes) {
            $booking->duration_minutes = (int) ($booking->service->duration_minutes ?: 30);
        }
        if (array_key_exists('mentee_note', $input)) {
            $booking->mentee_note = $input['mentee_note'] !== null ? (string) $input['mentee_note'] : null;
        }
    }

    /**
     * Auto-generates a Zoho Meeting join link for a session being confirmed
     * (fresh schedule, direct reschedule, or an approved reschedule request).
     *
     * If the caller already supplied a manual meeting_url (via applyScheduleFields),
     * that wins and Zoho is skipped entirely. Otherwise this tries Zoho and,
     * on any failure or if Zoho isn't connected, leaves meeting_url empty so
     * the mentor can still paste one in by hand — scheduling is never blocked
     * on Zoho being reachable.
     */
    private static function applyZohoMeetingLink(MentorBooking $booking, array $input): void
    {
        if (!empty($input['meeting_url'])) {
            $booking->meeting_link_source = 'manual';

            return;
        }

        $zoho = app(ZohoMeetingService::class);
        if (!$zoho->isConnected()) {
            return;
        }

        if (!empty($booking->zoho_meeting_id)) {
            // Time is changing (reschedule) — best-effort cleanup of the old meeting.
            $zoho->deleteMeeting($booking->zoho_meeting_id);
            $booking->zoho_meeting_id = null;
        }

        try {
            $result = $zoho->createMeeting($booking);
        } catch (\Throwable $e) {
            $result = null;
            Log::error('Zoho createMeeting threw during scheduling', ['booking_id' => $booking->id, 'message' => $e->getMessage()]);
        }

        if ($result) {
            $booking->meeting_url = $result['join_url'];
            $booking->zoho_meeting_id = $result['meeting_id'];
            $booking->meeting_link_source = 'zoho';
            $booking->zoho_last_error = null;
        } else {
            $booking->meeting_link_source = 'manual';
            $booking->zoho_last_error = 'Zoho meeting creation failed; add a meeting link manually.';
        }
    }

    private static function parseScheduleInput(array $input): Carbon
    {
        $date = (string) ($input['preferred_date'] ?? $input['start_date'] ?? '');
        $timeRaw = (string) ($input['preferred_time'] ?? $input['start_time'] ?? '');
        if ($date === '' || $timeRaw === '') {
            throw new \InvalidArgumentException('Date and time are required.');
        }

        $time = strlen($timeRaw) === 5 ? $timeRaw.':00' : $timeRaw;
        $tz = (string) ($input['timezone'] ?? self::appTimezone());

        return Carbon::parse($date.' '.$time, $tz);
    }

    private static function resetReminderFlags(MentorBooking $booking): void
    {
        if (Schema::hasColumn('mentor_bookings', 'session_reminder_24h_sent_at')) {
            $booking->session_reminder_24h_sent_at = null;
        }
        if (Schema::hasColumn('mentor_bookings', 'session_reminder_1h_sent_at')) {
            $booking->session_reminder_1h_sent_at = null;
        }
        if (Schema::hasColumn('mentor_bookings', 'schedule_notify_sent_at')) {
            $booking->schedule_notify_sent_at = null;
        }
    }
}
