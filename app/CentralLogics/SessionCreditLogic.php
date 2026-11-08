<?php

namespace App\CentralLogics;

use App\Model\Mentor\Mentor;
use App\Model\Mentor\MentorBooking;
use App\Model\Mentor\MentorService;
use App\Model\Mentor\MentorSessionCredit;
use App\Model\Mentor\MentorSessionCreditLedger;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class SessionCreditLogic
{
    public static function isSessionPackBooking(MentorBooking $booking): bool
    {
        $source = (string) ($booking->booking_source ?? 'paid');

        return in_array($source, ['credit', 'session'], true) && (bool) $booking->session_credit_id;
    }

    public static function normalizeBookingSourceForApi(?string $source): string
    {
        return ($source === 'credit') ? 'session' : (string) ($source ?: 'paid');
    }

    public static function computeSessionAmounts(MentorSessionCredit $credit): array
    {
        $remainingCredits = max(1, $credit->remaining());
        $remainingGross = $credit->fundRemaining();

        if ($remainingCredits === 1) {
            $sessionGross = $remainingGross;
        } else {
            $sessionGross = round($remainingGross / $remainingCredits, 2);
        }

        $feePercent = $credit->effectiveFeePercent();
        $platformFee = round($sessionGross * ($feePercent / 100), 2);
        $mentorNet = round($sessionGross - $platformFee, 2);

        return [
            'gross' => $sessionGross,
            'platform_fee' => $platformFee,
            'mentor_net' => $mentorNet,
            'platform_fee_percent' => $feePercent,
        ];
    }

    public static function grant(
        User $mentee,
        Mentor $mentor,
        int $count,
        float $totalAmountGross,
        ?int $adminId = null,
        ?string $note = null,
        ?float $feePercent = null
    ): MentorSessionCredit {
        if ($count < 1) {
            throw new \InvalidArgumentException('Session count must be at least 1.');
        }
        if ($totalAmountGross <= 0) {
            throw new \InvalidArgumentException('Total amount must be greater than zero.');
        }

        return DB::transaction(function () use ($mentee, $mentor, $count, $totalAmountGross, $adminId, $note, $feePercent) {
            $credit = MentorSessionCredit::query()
                ->where('mentee_user_id', $mentee->id)
                ->where('mentor_id', $mentor->id)
                ->lockForUpdate()
                ->first();

            if (!$credit) {
                $credit = MentorSessionCredit::create([
                    'mentee_user_id' => $mentee->id,
                    'mentor_id' => $mentor->id,
                    'credits_total' => 0,
                    'credits_used' => 0,
                    'fund_total' => 0,
                    'fund_used' => 0,
                    // Only set on brand-new packs. Topping up an existing pack
                    // keeps whatever rate it was created with — an admin
                    // changing the fee % on a top-up must not silently change
                    // the payout for sessions already funded in that pack.
                    'platform_fee_percent' => $feePercent,
                    'notes' => $note,
                    'granted_by_admin_id' => $adminId,
                ]);
                $credit = MentorSessionCredit::query()->whereKey($credit->id)->lockForUpdate()->first();
            }

            $credit->credits_total = (int) $credit->credits_total + $count;
            $credit->fund_total = round((float) $credit->fund_total + $totalAmountGross, 2);
            if ($note) {
                $credit->notes = $note;
            }
            if ($adminId) {
                $credit->granted_by_admin_id = $adminId;
            }
            // Admin manually granting session credits IS the payment record —
            // the money was already collected out-of-band (UPI/bank transfer)
            // before this action is taken. Confirm it immediately instead of
            // defaulting to 'pending' and relying on a separate admin click,
            // so earnings/payout totals (which only count confirmed packs)
            // reflect every manually-added session correctly from the start.
            if (!$credit->isPaymentConfirmed()) {
                $credit->payment_status = 'paid';
                $credit->payment_confirmed_at = now();
                $credit->payment_confirmed_by_admin_id = $adminId;
            }
            // Drives the "most recently assigned" sort on /admin/session-packs —
            // unlike updated_at, this is only ever touched here, never by
            // payment toggles or session-completion saves on the same row.
            $credit->last_granted_at = now();
            $credit->save();

            MentorSessionCreditLedger::create([
                'credit_id' => $credit->id,
                'type' => 'grant',
                'amount' => $count,
                'fund_gross' => round($totalAmountGross, 2),
                'admin_id' => $adminId,
                'note' => $note,
            ]);

            $credit = $credit->fresh(['mentor', 'mentee']);

            MentorSessionLifecycleLogic::ensurePackSessionRows($credit);
            MentorBookingMailLogic::sendPackageAssignedEmails($credit);

            return $credit;
        });
    }

    public static function available(MentorSessionCredit $credit): int
    {
        return $credit->availableToSchedule();
    }

    /**
     * @param  array{mode:string,start_date:string,start_time:string,count?:int,mentor_service_id?:int,mentee_note?:string}  $params
     * @return Collection<int, MentorBooking>
     */
    public static function scheduleSessions(MentorSessionCredit $credit, array $params): Collection
    {
        $mode = (string) ($params['mode'] ?? 'one_off');
        if (!in_array($mode, ['one_off', 'daily', 'weekly'], true)) {
            throw new \InvalidArgumentException('Invalid schedule mode.');
        }

        $count = $mode === 'one_off' ? 1 : max(1, (int) ($params['count'] ?? 1));
        $available = self::available($credit);
        if ($count > $available) {
            throw new \RuntimeException("Only {$available} session(s) available to schedule.");
        }

        $startDate = Carbon::parse($params['start_date'])->startOfDay();
        $timeRaw = (string) $params['start_time'];
        $time = strlen($timeRaw) === 5 ? $timeRaw.':00' : $timeRaw;
        $tz = MentorSessionLifecycleLogic::appTimezone();
        $firstStart = Carbon::parse($startDate->format('Y-m-d').' '.$time, $tz);
        if ($firstStart->lt(now($tz)->subMinute())) {
            throw new \InvalidArgumentException('Session date/time must be now or in the future.');
        }

        $credit->loadMissing('mentor');
        $mentor = $credit->mentor;
        if (!$mentor) {
            throw new \RuntimeException('Mentor not found for session pack.');
        }

        $service = self::resolveService($mentor, $params['mentor_service_id'] ?? null);

        $slots = [];
        for ($i = 0; $i < $count; $i++) {
            if ($mode === 'daily') {
                $when = $firstStart->copy()->addDays($i);
            } elseif ($mode === 'weekly') {
                $when = $firstStart->copy()->addWeeks($i);
            } else {
                $when = $firstStart->copy();
            }
            $slots[] = $when;
        }

        MentorSessionLifecycleLogic::ensurePackSessionRows($credit);

        return DB::transaction(function () use ($credit, $mentor, $service, $slots, $params) {
            $scheduled = collect();
            $assigned = MentorBooking::query()
                ->where('session_credit_id', $credit->id)
                ->where('status', 'assigned')
                ->orderBy('session_number')
                ->lockForUpdate()
                ->get();

            foreach ($slots as $index => $when) {
                $input = [
                    'preferred_date' => $when->toDateString(),
                    'preferred_time' => $when->format('H:i:s'),
                    'mentee_note' => $params['mentee_note'] ?? 'Scheduled from session pack',
                    'timezone' => MentorSessionLifecycleLogic::appTimezone(),
                ];
                if (!empty($params['duration_minutes'])) {
                    $input['duration_minutes'] = max(5, (int) $params['duration_minutes']);
                } elseif (!empty($params['mentor_service_id'])) {
                    $service = self::resolveService($mentor, $params['mentor_service_id']);
                    $input['duration_minutes'] = (int) ($service->duration_minutes ?: 30);
                } else {
                    $input['duration_minutes'] = (int) ($service->duration_minutes ?: 30);
                }

                $assignedRow = $assigned->get($index);
                if ($assignedRow) {
                    $booking = MentorSessionLifecycleLogic::scheduleSession($assignedRow, $input, null);
                    $scheduled->push($booking);
                    continue;
                }

                $amounts = self::computeSessionAmounts($credit);
                $duration = !empty($params['duration_minutes'])
                    ? max(5, (int) $params['duration_minutes'])
                    : (int) ($service->duration_minutes ?: 30);
                $booking = MentorBooking::create([
                    'mentor_id' => $mentor->id,
                    'mentor_service_id' => $service->id,
                    'mentee_user_id' => $credit->mentee_user_id,
                    'session_credit_id' => $credit->id,
                    'preferred_date' => $when->toDateString(),
                    'preferred_time' => $when->format('H:i:s'),
                    'scheduled_at' => $when->copy()->timezone('UTC'),
                    'timezone' => MentorSessionLifecycleLogic::appTimezone(),
                    'duration_minutes' => $duration,
                    'mentee_note' => $params['mentee_note'] ?? 'Scheduled from session pack',
                    'status' => 'confirmed',
                    'amount' => $amounts['gross'],
                    'tax_amount' => 0,
                    'platform_fee' => $amounts['platform_fee'],
                    'mentor_net' => $amounts['mentor_net'],
                    'payment_status' => 'paid',
                    'booking_source' => 'session',
                ]);
                MentorSessionLifecycleLogic::recordEvent($booking, null, 'session_scheduled', [
                    'scheduled_at' => $booking->scheduled_at?->toIso8601String(),
                ]);
                MentorBookingMailLogic::sendScheduleConfirmedNotify(
                    $booking->fresh(['mentor.user', 'service', 'mentee']),
                    true
                );
                UserNotificationLogic::notifyScheduled($booking->fresh(['mentor.user', 'service', 'mentee']));
                $scheduled->push($booking);
            }

            return $scheduled;
        });
    }

    public static function sessionDateTime(MentorBooking $booking): ?Carbon
    {
        if ($booking->scheduled_at) {
            try {
                // scheduled_at is written as a UTC instant (see applyScheduleFields),
                // but Eloquent's datetime cast re-labels it using app.timezone
                // (Asia/Calcutta) on read, so $booking->scheduled_at already carries
                // a wrong instant (shifted by the IST offset). Re-tag the wall-clock
                // digits as UTC directly instead of calling ->utc(), which would
                // incorrectly shift it a second time.
                return Carbon::parse($booking->scheduled_at->format('Y-m-d H:i:s'), 'UTC');
            } catch (\Throwable $e) {
                // fall through to preferred_date/time
            }
        }

        if (!$booking->preferred_date || !$booking->preferred_time) {
            return null;
        }

        $date = $booking->preferred_date instanceof Carbon
            ? $booking->preferred_date->format('Y-m-d')
            : (string) $booking->preferred_date;
        $time = (string) $booking->preferred_time;
        if (strlen($time) === 5) {
            $time .= ':00';
        }

        try {
            $tz = $booking->timezone ?: MentorSessionLifecycleLogic::appTimezone();

            return Carbon::parse($date.' '.$time, $tz);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function isUpcoming(MentorBooking $booking): bool
    {
        return in_array((string) $booking->status, ['assigned', 'requested', 'confirmed', 'reschedule_requested'], true);
    }

    public static function isPast(MentorBooking $booking): bool
    {
        return in_array((string) $booking->status, ['completed', 'cancelled', 'refunded'], true);
    }

    public static function canReschedule(MentorBooking $booking): bool
    {
        return in_array((string) $booking->status, ['confirmed', 'reschedule_requested'], true);
    }

    public static function canSchedule(MentorBooking $booking): bool
    {
        return (string) $booking->status === 'assigned';
    }

    public static function canRequestReschedule(MentorBooking $booking): bool
    {
        return (string) $booking->status === 'confirmed';
    }

    public static function canApproveReschedule(MentorBooking $booking): bool
    {
        return (string) $booking->status === 'reschedule_requested';
    }

    public static function canMarkComplete(MentorBooking $booking): bool
    {
        if (!in_array((string) $booking->status, ['requested', 'confirmed', 'reschedule_requested'], true)) {
            return false;
        }
        $when = self::sessionDateTime($booking);
        if (!$when) {
            return false;
        }

        return $when->lte(now());
    }

    /**
     * Admin can mark complete without waiting for session datetime,
     * including unscheduled pack slots (assigned).
     */
    public static function canAdminMarkComplete(MentorBooking $booking): bool
    {
        return in_array((string) $booking->status, ['assigned', 'requested', 'confirmed', 'reschedule_requested'], true);
    }

    public static function canCancel(MentorBooking $booking): bool
    {
        return in_array((string) $booking->status, ['assigned', 'requested', 'confirmed', 'reschedule_requested'], true);
    }

    public static function canMarkNoShow(MentorBooking $booking): bool
    {
        if ((string) $booking->status !== 'confirmed') {
            return false;
        }
        $when = self::sessionDateTime($booking);

        return $when !== null && $when->lte(now());
    }

    public static function canJoinSession(MentorBooking $booking, int $minutesBefore = 15): bool
    {
        if ((string) $booking->status !== 'confirmed' || !$booking->meeting_url) {
            return false;
        }
        $when = self::sessionDateTime($booking);
        if (!$when) {
            return false;
        }
        $duration = (int) ($booking->duration_minutes ?? $booking->service?->duration_minutes ?? 30);

        return now()->gte($when->copy()->subMinutes($minutesBefore))
            && now()->lte($when->copy()->addMinutes(max(5, $duration)));
    }

    public static function hadConsumedCredit(MentorBooking $booking): bool
    {
        if (!self::isSessionPackBooking($booking)) {
            return false;
        }

        return MentorSessionCreditLedger::query()
            ->where('mentor_booking_id', $booking->id)
            ->where('type', 'consume')
            ->exists();
    }

    public static function restoreOnCancel(MentorBooking $booking): void
    {
        if (!self::isSessionPackBooking($booking)) {
            return;
        }

        DB::transaction(function () use ($booking) {
            $ledger = MentorSessionCreditLedger::query()
                ->where('mentor_booking_id', $booking->id)
                ->where('type', 'consume')
                ->lockForUpdate()
                ->first();
            if (!$ledger) {
                return;
            }

            $credit = MentorSessionCredit::query()->whereKey($booking->session_credit_id)->lockForUpdate()->first();
            if (!$credit) {
                return;
            }

            $credit->credits_used = max(0, (int) $credit->credits_used - (int) $ledger->amount);
            $credit->fund_used = max(0, round((float) $credit->fund_used - (float) $ledger->fund_gross, 2));
            $credit->save();
            $ledger->delete();
        });
    }

    public static function canUnmarkComplete(MentorBooking $booking): bool
    {
        // Once the mentor has been marked paid for this session, reversing
        // completion would silently delete the earning it was paid against
        // with no reconciliation — block it instead. Admin must mark the
        // payout pending again first if a correction is genuinely needed.
        if ((string) $booking->mentor_payout_status === 'paid') {
            return false;
        }

        return (string) $booking->status === 'completed';
    }

    /**
     * Admin correction for a session wrongly marked complete: reverses the
     * credit/earnings it generated and puts it back into the schedule.
     */
    public static function unmarkComplete(MentorBooking $booking): MentorBooking
    {
        if ((string) $booking->mentor_payout_status === 'paid') {
            throw new \RuntimeException('This session\'s mentor payout is already marked paid — mark the payout pending again first if you need to reverse completion.');
        }
        if (!self::canUnmarkComplete($booking)) {
            throw new \RuntimeException('Only a completed session can be marked incomplete.');
        }

        DB::transaction(function () use ($booking) {
            if (self::isSessionPackBooking($booking)) {
                $ledger = MentorSessionCreditLedger::query()
                    ->where('mentor_booking_id', $booking->id)
                    ->where('type', 'consume')
                    ->lockForUpdate()
                    ->first();

                if ($ledger) {
                    $credit = MentorSessionCredit::query()->whereKey($booking->session_credit_id)->lockForUpdate()->first();
                    if ($credit) {
                        $credit->credits_used = max(0, (int) $credit->credits_used - (int) $ledger->amount);
                        $credit->fund_used = max(0, round((float) $credit->fund_used - (float) $ledger->fund_gross, 2));
                        $credit->save();
                    }
                    $ledger->delete();
                }

                $booking->earnings()->delete();
                $booking->amount = 0;
                $booking->tax_amount = 0;
                $booking->platform_fee = 0;
                $booking->mentor_net = 0;
            }

            $booking->status = $booking->scheduled_at ? 'confirmed' : 'assigned';
            $booking->completed_at = null;
            if (\Illuminate\Support\Facades\Schema::hasColumn('mentor_bookings', 'completed_by_user_id')) {
                $booking->completed_by_user_id = null;
            }
            $booking->save();
        });

        return $booking->fresh(['service', 'mentor', 'mentee']);
    }

    public static function markComplete(MentorBooking $booking, bool $asAdmin = false): MentorBooking
    {
        if ($asAdmin) {
            if (!self::canAdminMarkComplete($booking)) {
                throw new \RuntimeException('This session cannot be marked complete.');
            }
        } elseif (!self::canMarkComplete($booking)) {
            throw new \RuntimeException('Session can only be marked complete after its scheduled date and time.');
        }

        $booking->status = 'completed';
        if (\Illuminate\Support\Facades\Schema::hasColumn('mentor_bookings', 'completed_at')) {
            $booking->completed_at = now();
        }
        if (\Illuminate\Support\Facades\Schema::hasColumn('mentor_bookings', 'completed_by_user_id') && auth()->id()) {
            $booking->completed_by_user_id = auth()->id();
        }
        $booking->save();

        self::consumeOnComplete($booking);

        return $booking->fresh(['service', 'mentor', 'mentee']);
    }

    public static function consumeOnComplete(MentorBooking $booking): void
    {
        if (!self::isSessionPackBooking($booking)) {
            return;
        }

        DB::transaction(function () use ($booking) {
            $exists = MentorSessionCreditLedger::query()
                ->where('mentor_booking_id', $booking->id)
                ->where('type', 'consume')
                ->lockForUpdate()
                ->exists();
            if ($exists) {
                return;
            }

            $credit = MentorSessionCredit::query()
                ->whereKey($booking->session_credit_id)
                ->lockForUpdate()
                ->first();
            if (!$credit) {
                return;
            }

            $amounts = self::computeSessionAmounts($credit);
            $sessionGross = $amounts['gross'];
            $platformFee = $amounts['platform_fee'];
            $mentorNet = $amounts['mentor_net'];

            $credit->credits_used = min((int) $credit->credits_total, (int) $credit->credits_used + 1);
            $credit->fund_used = round((float) $credit->fund_used + $sessionGross, 2);
            $credit->save();

            $booking->amount = $sessionGross;
            $booking->tax_amount = 0;
            $booking->platform_fee = $platformFee;
            $booking->mentor_net = $mentorNet;
            $booking->save();

            MentorSessionCreditLedger::create([
                'credit_id' => $credit->id,
                'type' => 'consume',
                'amount' => 1,
                'fund_gross' => $sessionGross,
                'fund_net' => $mentorNet,
                'mentor_booking_id' => $booking->id,
                'note' => 'Session completed',
            ]);

            $booking->loadMissing('mentor');
            // Only count this session toward earnings/payout once its pack's
            // payment is confirmed — an unconfirmed pack must not inflate
            // what a mentor can request as payout. See confirmPackEarnings()
            // for the backfill that runs once payment is later confirmed.
            if ($credit->isPaymentConfirmed() && !$booking->earnings()->exists()) {
                MentorEarningsLogic::creditBooking($booking);
            }
        });
    }

    /**
     * Backfill earnings for any already-completed sessions in a pack whose
     * payment has just been confirmed (admin clicked "Confirm payment"
     * after sessions were already delivered).
     */
    public static function confirmPackEarnings(MentorSessionCredit $credit): void
    {
        $credit->loadMissing('bookings.mentor');
        foreach ($credit->bookings as $booking) {
            if ((string) $booking->status !== 'completed') {
                continue;
            }
            if ($booking->earnings()->exists()) {
                continue;
            }
            MentorEarningsLogic::creditBooking($booking);
        }
    }

    /**
     * Admin-only: record that a specific completed session's mentor payout
     * has (or hasn't) actually been settled. Independent of MentorPayout
     * (the mentor's own lump-sum withdrawal requests) — this is admin
     * directly confirming a session is paid, e.g. after a bank transfer
     * made outside the app.
     */
    public static function toggleMentorPayout(
        MentorBooking $booking,
        ?int $adminId,
        ?string $reference = null,
        ?string $method = null
    ): MentorBooking {
        if ((string) $booking->status !== 'completed') {
            throw new \RuntimeException('Only a completed session can have its payout marked.');
        }

        if ((string) $booking->mentor_payout_status === 'paid') {
            $booking->mentor_payout_status = 'pending';
            $booking->mentor_paid_at = null;
            $booking->mentor_paid_by_admin_id = null;
            $booking->mentor_payout_method = null;
            $booking->mentor_payout_reference = null;
        } else {
            $booking->mentor_payout_status = 'paid';
            $booking->mentor_paid_at = now();
            $booking->mentor_paid_by_admin_id = $adminId;
            $booking->mentor_payout_method = $method ?: 'upi';
            $booking->mentor_payout_reference = $reference ?: null;
        }
        $booking->save();

        return $booking->fresh();
    }

    public static function applyBucketFilter($query, ?string $bucket)
    {
        $bucket = strtolower((string) $bucket);
        if ($bucket === 'pending_schedule') {
            return $query->where(function ($q) {
                $q->where('status', 'assigned')
                    ->orWhere(function ($q2) {
                        $q2->whereIn('booking_source', ['credit', 'session'])
                            ->whereNotNull('session_credit_id')
                            ->whereNull('scheduled_at');
                    });
            });
        }
        if ($bucket === 'upcoming') {
            return $query->whereIn('status', ['assigned', 'requested', 'confirmed', 'reschedule_requested']);
        }
        if ($bucket === 'past') {
            return $query->whereIn('status', ['completed', 'cancelled', 'refunded']);
        }

        return $query;
    }

    public static function formatSessionPack(MentorSessionCredit $credit): array
    {
        $credit->loadMissing(['mentor', 'mentee']);
        $sessionsTotal = (int) $credit->credits_total;
        $sessionsUsed = (int) $credit->credits_used;
        $sessionsRemaining = $credit->remaining();
        $sessionRateGross = $credit->currentSessionGrossRate();
        $sessionRateNet = $credit->currentSessionNetRate();
        $fundTotal = round((float) ($credit->fund_total ?? 0), 2);
        $fundRemaining = $credit->fundRemaining();
        $feePercent = $credit->effectiveFeePercent();
        $totalPotentialNet = round($sessionsRemaining * $sessionRateNet, 2);

        $payload = [
            'id' => $credit->id,
            'mentee_user_id' => $credit->mentee_user_id,
            'mentor_id' => $credit->mentor_id,
            'session_pack_id' => $credit->id,
            'mentor' => $credit->mentor ? [
                'id' => $credit->mentor->id,
                'username' => $credit->mentor->username,
                'display_name' => $credit->mentor->display_name,
            ] : null,
            'mentee' => $credit->mentee ? [
                'id' => $credit->mentee->id,
                'name' => trim(($credit->mentee->f_name ?? '').' '.($credit->mentee->l_name ?? '')),
                'first_name' => SessionChatLogic::firstName($credit->mentee->f_name ?? ''),
            ] : null,
            'sessions_total' => $sessionsTotal,
            'sessions_used' => $sessionsUsed,
            'sessions_remaining' => $sessionsRemaining,
            'session_rate_gross' => $sessionRateGross,
            'session_rate_net' => $sessionRateNet,
            'fund_total' => $fundTotal,
            'fund_used' => round((float) ($credit->fund_used ?? 0), 2),
            'fund_remaining' => $fundRemaining,
            'total_potential_net' => $totalPotentialNet,
            'platform_fee_percent' => $feePercent,
            'open_scheduled' => $credit->openScheduledCount(),
            'available_to_schedule' => $credit->availableToSchedule(),
            'notes' => $credit->notes,
            'updated_at' => $credit->updated_at?->toIso8601String(),
            // Deprecated aliases — remove after clients migrate
            'credits_total' => $sessionsTotal,
            'credits_used' => $sessionsUsed,
            'credits_remaining' => $sessionsRemaining,
            'session_gross_rate' => $sessionRateGross,
            'session_net_rate' => $sessionRateNet,
        ];

        return $payload;
    }

    /** @deprecated Use formatSessionPack */
    public static function formatCredit(MentorSessionCredit $credit): array
    {
        return self::formatSessionPack($credit);
    }

    public static function sessionsForMentee(int $menteeUserId): Collection
    {
        return MentorSessionCredit::query()
            ->with(['mentor'])
            ->where('mentee_user_id', $menteeUserId)
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (MentorSessionCredit $c) => self::formatSessionPack($c));
    }

    public static function sessionsForMentor(int $mentorId): Collection
    {
        return MentorSessionCredit::query()
            ->with(['mentee', 'mentor'])
            ->where('mentor_id', $mentorId)
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (MentorSessionCredit $c) => self::formatSessionPack($c));
    }

    /** @deprecated Use sessionsForMentee */
    public static function creditsForMentee(int $menteeUserId): Collection
    {
        return self::sessionsForMentee($menteeUserId);
    }

    /** @deprecated Use sessionsForMentor */
    public static function creditsForMentor(int $mentorId): Collection
    {
        return self::sessionsForMentor($mentorId);
    }

    public static function resolveService(Mentor $mentor, $serviceId = null): MentorService
    {
        if ($serviceId) {
            $service = MentorService::query()
                ->where('mentor_id', $mentor->id)
                ->where('id', (int) $serviceId)
                ->first();
            if ($service) {
                return $service;
            }
        }

        $service = MentorService::query()
            ->where('mentor_id', $mentor->id)
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->first();

        if ($service) {
            return $service;
        }

        $service = MentorService::query()
            ->where('mentor_id', $mentor->id)
            ->orderBy('sort_order')
            ->first();

        if ($service) {
            return $service;
        }

        return MentorService::create([
            'mentor_id' => $mentor->id,
            'title' => 'Mentorship session',
            'description' => 'Session pack booking',
            'duration_minutes' => 30,
            'price' => 0,
            'is_enabled' => true,
            'sort_order' => 0,
            'meeting_type' => 'online',
        ]);
    }
}
