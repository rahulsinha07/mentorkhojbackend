<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\SessionCreditLogic;
use App\Http\Controllers\Controller;
use App\Model\Mentor\MentorBooking;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

class MentorScheduleAdminController extends Controller
{
    /**
     * scheduled_at is stored as a UTC instant (see
     * MentorSessionLifecycleLogic::applyScheduleFields), so raw-string
     * comparison/ordering against a UTC "now" is correct even though
     * Eloquent's datetime cast mislabels it with app.timezone on read.
     */
    public function index(): View
    {
        $nowUtc = now()->utc()->format('Y-m-d H:i:s');
        $upcomingWindowEnd = now()->utc()->addDays(14)->format('Y-m-d H:i:s');
        $pastWindowStart = now()->utc()->subDays(30)->format('Y-m-d H:i:s');

        $upcomingBookings = MentorBooking::query()
            ->where('status', 'confirmed')
            ->whereNotNull('scheduled_at')
            ->whereRaw('scheduled_at <= ?', [$upcomingWindowEnd])
            ->orderBy('scheduled_at', 'asc')
            ->with(['mentor', 'mentee', 'service'])
            ->get();

        $pastBookings = MentorBooking::query()
            ->whereIn('status', ['confirmed', 'completed', 'no_show'])
            ->whereNotNull('scheduled_at')
            ->whereRaw('scheduled_at >= ?', [$pastWindowStart])
            ->whereRaw('scheduled_at <= ?', [$nowUtc])
            ->orderBy('scheduled_at', 'desc')
            ->limit(100)
            ->with(['mentor', 'mentee', 'service'])
            ->get();

        $upcoming = $this->rows($upcomingBookings, $nowUtc)->reject(fn (array $row) => $row['is_ended'])->values();
        $past = $this->rows($pastBookings, $nowUtc)->filter(fn (array $row) => $row['is_ended'] || $row['booking']->status !== 'confirmed')->values();

        return view('admin-views.schedules.index', ['upcoming' => $upcoming, 'past' => $past]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function rows(Collection $bookings, string $nowUtc): Collection
    {
        return $bookings->map(function (MentorBooking $booking) use ($nowUtc) {
            $when = SessionCreditLogic::sessionDateTime($booking);
            $rawScheduledAt = $booking->getAttributes()['scheduled_at'] ?? null;
            $isLive = $rawScheduledAt !== null && $rawScheduledAt <= $nowUtc;

            $duration = (int) ($booking->duration_minutes ?? $booking->service?->duration_minutes ?? 30);
            // A session whose scheduled time + duration (+ a short grace period)
            // has already passed is over — Zoho expires its join link at that
            // point too, so stop showing it as joinable once past.
            $isEnded = $when !== null && now()->gt($when->copy()->addMinutes($duration + 15));

            return [
                'booking' => $booking,
                'when' => $when,
                'mentor_name' => $booking->mentor?->display_name ?? '—',
                'mentee_name' => trim(($booking->mentee?->f_name ?? '').' '.($booking->mentee?->l_name ?? '')) ?: '—',
                'meeting_url' => $booking->meeting_url,
                'meeting_link_source' => $booking->meeting_link_source,
                'zoho_last_error' => $booking->zoho_last_error,
                'can_join' => SessionCreditLogic::canJoinSession($booking),
                'is_live' => $isLive,
                'is_ended' => $isEnded,
                'status' => $booking->status,
            ];
        });
    }
}
