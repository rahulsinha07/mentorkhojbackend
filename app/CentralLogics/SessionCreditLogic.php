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
    public static function grant(
        User $mentee,
        Mentor $mentor,
        int $count,
        ?int $adminId = null,
        ?string $note = null
    ): MentorSessionCredit {
        if ($count < 1) {
            throw new \InvalidArgumentException('Credit count must be at least 1.');
        }

        return DB::transaction(function () use ($mentee, $mentor, $count, $adminId, $note) {
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
                    'notes' => $note,
                    'granted_by_admin_id' => $adminId,
                ]);
                $credit = MentorSessionCredit::query()->whereKey($credit->id)->lockForUpdate()->first();
            }

            $credit->credits_total = (int) $credit->credits_total + $count;
            if ($note) {
                $credit->notes = $note;
            }
            if ($adminId) {
                $credit->granted_by_admin_id = $adminId;
            }
            $credit->save();

            MentorSessionCreditLedger::create([
                'credit_id' => $credit->id,
                'type' => 'grant',
                'amount' => $count,
                'admin_id' => $adminId,
                'note' => $note,
            ]);

            return $credit->fresh(['mentor', 'mentee']);
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
            throw new \RuntimeException("Only {$available} credit(s) available to schedule.");
        }

        $startDate = Carbon::parse($params['start_date'])->startOfDay();
        $timeRaw = (string) $params['start_time'];
        $time = strlen($timeRaw) === 5 ? $timeRaw.':00' : $timeRaw;
        $firstStart = Carbon::parse($startDate->format('Y-m-d').' '.$time);
        if ($firstStart->lt(now()->subMinute())) {
            throw new \InvalidArgumentException('Session date/time must be now or in the future.');
        }

        $credit->loadMissing('mentor');
        $mentor = $credit->mentor;
        if (!$mentor) {
            throw new \RuntimeException('Mentor not found for credit pack.');
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

        return DB::transaction(function () use ($credit, $mentor, $service, $slots, $params) {
            $created = collect();
            foreach ($slots as $when) {
                $booking = MentorBooking::create([
                    'mentor_id' => $mentor->id,
                    'mentor_service_id' => $service->id,
                    'mentee_user_id' => $credit->mentee_user_id,
                    'session_credit_id' => $credit->id,
                    'preferred_date' => $when->toDateString(),
                    'preferred_time' => $when->format('H:i:s'),
                    'mentee_note' => $params['mentee_note'] ?? 'Scheduled from session credits',
                    'status' => 'confirmed',
                    'amount' => 0,
                    'tax_amount' => 0,
                    'platform_fee' => 0,
                    'mentor_net' => 0,
                    'payment_status' => 'paid',
                    'booking_source' => 'credit',
                ]);
                $created->push($booking);
            }

            return $created;
        });
    }

    public static function sessionDateTime(MentorBooking $booking): ?Carbon
    {
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
            return Carbon::parse($date.' '.$time);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function isUpcoming(MentorBooking $booking): bool
    {
        return in_array((string) $booking->status, ['requested', 'confirmed'], true);
    }

    public static function isPast(MentorBooking $booking): bool
    {
        return in_array((string) $booking->status, ['completed', 'cancelled', 'refunded'], true);
    }

    public static function canReschedule(MentorBooking $booking): bool
    {
        return self::isUpcoming($booking);
    }

    public static function canMarkComplete(MentorBooking $booking): bool
    {
        if (!in_array((string) $booking->status, ['requested', 'confirmed'], true)) {
            return false;
        }
        $when = self::sessionDateTime($booking);
        if (!$when) {
            return false;
        }

        return $when->lte(now());
    }

    public static function markComplete(MentorBooking $booking): MentorBooking
    {
        if (!self::canMarkComplete($booking)) {
            throw new \RuntimeException('Session can only be marked complete after its scheduled date and time.');
        }

        $booking->status = 'completed';
        $booking->save();

        self::consumeOnComplete($booking);

        return $booking->fresh(['service', 'mentor', 'mentee']);
    }

    public static function consumeOnComplete(MentorBooking $booking): void
    {
        if (($booking->booking_source ?? 'paid') !== 'credit' || !$booking->session_credit_id) {
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

            $credit->credits_used = min((int) $credit->credits_total, (int) $credit->credits_used + 1);
            $credit->save();

            MentorSessionCreditLedger::create([
                'credit_id' => $credit->id,
                'type' => 'consume',
                'amount' => 1,
                'mentor_booking_id' => $booking->id,
                'note' => 'Session completed',
            ]);
        });
    }

    public static function applyBucketFilter($query, ?string $bucket)
    {
        $bucket = strtolower((string) $bucket);
        if ($bucket === 'upcoming') {
            return $query->whereIn('status', ['requested', 'confirmed']);
        }
        if ($bucket === 'past') {
            return $query->whereIn('status', ['completed', 'cancelled', 'refunded']);
        }

        return $query;
    }

    public static function formatCredit(MentorSessionCredit $credit): array
    {
        $credit->loadMissing(['mentor', 'mentee']);

        return [
            'id' => $credit->id,
            'mentee_user_id' => $credit->mentee_user_id,
            'mentor_id' => $credit->mentor_id,
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
            'credits_total' => (int) $credit->credits_total,
            'credits_used' => (int) $credit->credits_used,
            'credits_remaining' => $credit->remaining(),
            'open_scheduled' => $credit->openScheduledCount(),
            'available_to_schedule' => $credit->availableToSchedule(),
            'notes' => $credit->notes,
            'updated_at' => $credit->updated_at?->toIso8601String(),
        ];
    }

    public static function creditsForMentee(int $menteeUserId): Collection
    {
        return MentorSessionCredit::query()
            ->with(['mentor'])
            ->where('mentee_user_id', $menteeUserId)
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (MentorSessionCredit $c) => self::formatCredit($c));
    }

    public static function creditsForMentor(int $mentorId): Collection
    {
        return MentorSessionCredit::query()
            ->with(['mentee', 'mentor'])
            ->where('mentor_id', $mentorId)
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (MentorSessionCredit $c) => self::formatCredit($c));
    }

    private static function resolveService(Mentor $mentor, $serviceId = null): MentorService
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
            'description' => 'Session credit booking',
            'duration_minutes' => 30,
            'price' => 0,
            'is_enabled' => true,
            'sort_order' => 0,
            'meeting_type' => 'online',
        ]);
    }
}
