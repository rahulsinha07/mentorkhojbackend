<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\CentralLogics\MentorBookingMailLogic;
use App\CentralLogics\SessionCreditLogic;
use App\Http\Controllers\Controller;
use App\Model\Mentor\Mentor;
use App\Model\Mentor\MentorBooking;
use App\Model\Mentor\MentorSessionNotification;
use App\User;
use App\CentralLogics\MentorSessionLifecycleLogic;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MentorBookingController extends Controller
{
    private const STATUSES = ['requested', 'confirmed', 'assigned', 'reschedule_requested', 'completed', 'cancelled', 'refunded', 'no_show'];

    private const UPCOMING_STATUSES = ['assigned', 'requested', 'confirmed', 'reschedule_requested'];

    private const PAST_STATUSES = ['completed', 'cancelled', 'refunded', 'no_show'];

    public function list(Request $request): View
    {
        $tab = strtolower((string) $request->get('tab', 'all'));
        if (!in_array($tab, ['all', 'upcoming', 'past'], true)) {
            $tab = 'all';
        }

        $search = trim((string) $request->get('search', ''));
        $mentorId = (int) $request->get('mentor_id', 0) ?: null;
        $menteeId = (int) $request->get('mentee_id', 0) ?: null;

        $query = MentorBooking::with(['mentor.user', 'service', 'mentee']);

        if ($tab === 'upcoming') {
            $query->whereIn('status', self::UPCOMING_STATUSES);
        } elseif ($tab === 'past') {
            $query->whereIn('status', self::PAST_STATUSES);
        }

        if ($mentorId) {
            $query->where('mentor_id', $mentorId);
        }
        if ($menteeId) {
            $query->where('mentee_user_id', $menteeId);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhereHas('mentor', fn ($m) => $m->where('display_name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%"))
                    ->orWhereHas('mentee', function ($u) use ($search) {
                        $u->where('f_name', 'like', "%{$search}%")
                            ->orWhere('l_name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        // All: newest first; Upcoming: soonest first; Past: most recently scheduled first
        if ($tab === 'all') {
            $query->orderByDesc('id');
        } elseif ($tab === 'past') {
            $query->orderByDesc('preferred_date')->orderByDesc('id');
        } else {
            $query->orderByRaw('preferred_date IS NULL')
                ->orderBy('preferred_date')
                ->orderBy('preferred_time')
                ->orderByDesc('id');
        }

        $bookings = $query->paginate(Helpers::getPagination())->appends($request->query());

        $mentors = Mentor::query()
            ->orderBy('display_name')
            ->get(['id', 'display_name', 'username']);

        $menteeIds = MentorBooking::query()
            ->whereNotNull('mentee_user_id')
            ->distinct()
            ->pluck('mentee_user_id');
        $students = User::query()
            ->whereIn('id', $menteeIds)
            ->orderBy('f_name')
            ->orderBy('l_name')
            ->get(['id', 'f_name', 'l_name', 'phone']);

        $allCount = MentorBooking::count();
        $upcomingCount = MentorBooking::whereIn('status', self::UPCOMING_STATUSES)->count();
        $pastCount = MentorBooking::whereIn('status', self::PAST_STATUSES)->count();

        return view('admin-views.mentor.bookings.list', compact(
            'bookings',
            'search',
            'tab',
            'mentorId',
            'menteeId',
            'mentors',
            'students',
            'allCount',
            'upcomingCount',
            'pastCount'
        ));
    }

    public function show(int $id): View
    {
        $booking = MentorBooking::with(['mentor.user', 'mentor.settings', 'service', 'mentee', 'earnings'])->findOrFail($id);

        return view('admin-views.mentor.bookings.view', compact('booking'));
    }

    public function sendPaymentReminder(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'payment_link' => 'nullable|url|max:2000',
        ]);

        $booking = MentorBooking::with(['mentor', 'service', 'mentee'])->findOrFail($id);

        if (!in_array($booking->payment_status, ['pending', 'failed'], true)) {
            Toastr::error(translate('Payment is not pending for this booking'));
            return back();
        }

        $sent = MentorBookingMailLogic::sendPaymentReminderEmail(
            $booking,
            $validated['payment_link'] ?? null
        );

        if ($sent) {
            Toastr::success(translate('Payment reminder email sent'));
        } else {
            Toastr::error(translate('Failed to send payment reminder email'));
        }

        return back();
    }

    public function complete(int $id): RedirectResponse
    {
        $booking = MentorBooking::findOrFail($id);

        try {
            SessionCreditLogic::markComplete($booking, true);
            Toastr::success(translate('Session marked complete'));
        } catch (\Throwable $e) {
            Toastr::error($e->getMessage());
        }

        return back();
    }

    public function toggleMentorPayout(Request $request, int $id): RedirectResponse
    {
        $booking = MentorBooking::findOrFail($id);

        try {
            $booking = SessionCreditLogic::toggleMentorPayout(
                $booking,
                auth('admin')->id(),
                $request->input('reference'),
                $request->input('method')
            );
            Toastr::success((string) $booking->mentor_payout_status === 'paid'
                ? translate('Mentor payout marked as paid')
                : translate('Mentor payout marked as pending'));
        } catch (\Throwable $e) {
            Toastr::error($e->getMessage());
        }

        return back();
    }

    public function reschedule(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'preferred_date' => 'required|date',
            'preferred_time' => 'required|string|max:32',
        ]);

        $booking = MentorBooking::with(['mentor.user', 'service', 'mentee'])->findOrFail($id);
        if (!SessionCreditLogic::canReschedule($booking)) {
            Toastr::error(translate('Only upcoming sessions can be rescheduled'));
            return back();
        }

        $timeRaw = (string) $validated['preferred_time'];
        $time = strlen($timeRaw) === 5 ? $timeRaw.':00' : $timeRaw;
        try {
            $when = \Carbon\Carbon::parse($validated['preferred_date'].' '.$time);
        } catch (\Throwable $e) {
            Toastr::error(translate('Invalid date or time'));
            return back();
        }
        if ($when->lt(now()->subMinute())) {
            Toastr::error(translate('Choose a date and time from now onward'));
            return back();
        }

        $booking->preferred_date = $validated['preferred_date'];
        $booking->preferred_time = $time;
        if ($booking->status === 'requested') {
            $booking->status = 'confirmed';
        }
        if (\Illuminate\Support\Facades\Schema::hasColumn('mentor_bookings', 'session_reminder_24h_sent_at')) {
            $booking->session_reminder_24h_sent_at = null;
        }
        $booking->save();
        MentorBookingMailLogic::sendScheduleConfirmedNotify($booking, true);

        Toastr::success(translate('Session rescheduled'));
        return back();
    }


    public function analytics(Request $request): View
    {
        $mentorId = $request->get('mentor_id');
        $menteeId = $request->get('mentee_id');
        $status = strtolower((string) $request->get('status', ''));
        $from = $request->get('from');
        $to = $request->get('to');

        $query = MentorBooking::query()->with(['mentor', 'mentee', 'service']);
        if ($mentorId) {
            $query->where('mentor_id', $mentorId);
        }
        if ($menteeId) {
            $query->where('mentee_user_id', $menteeId);
        }
        if ($status && in_array($status, self::STATUSES, true)) {
            $query->where('status', $status);
        }
        if ($from) {
            $query->whereDate('preferred_date', '>=', $from);
        }
        if ($to) {
            $query->whereDate('preferred_date', '<=', $to);
        }

        $stats = [
            'scheduled' => (clone $query)->whereIn('status', ['confirmed', 'reschedule_requested'])->count(),
            'pending_schedule' => (clone $query)->where('status', 'assigned')->count(),
            'completed' => (clone $query)->where('status', 'completed')->count(),
            'cancelled' => (clone $query)->whereIn('status', ['cancelled', 'refunded'])->count(),
            'no_show' => (clone $query)->where('status', 'no_show')->count(),
            'reminders_sent' => MentorSessionNotification::query()
                ->whereIn('notification_type', ['session_reminder_24h', 'session_reminder_1h'])
                ->where('status', 'sent')
                ->count(),
            'reminders_failed' => MentorSessionNotification::query()
                ->whereIn('notification_type', ['session_reminder_24h', 'session_reminder_1h'])
                ->where('status', 'failed')
                ->count(),
        ];

        $bookings = $query->latest()->paginate(Helpers::getPagination())->appends($request->query());

        return view('admin-views.mentor.bookings.analytics', compact('bookings', 'stats', 'status', 'mentorId', 'menteeId', 'from', 'to'));
    }

    public function markNoShowAdmin(int $id): RedirectResponse
    {
        $booking = MentorBooking::findOrFail($id);

        try {
            MentorSessionLifecycleLogic::markNoShow($booking, null, 'Marked no-show by admin');
            Toastr::success(translate('Session marked as no-show'));
        } catch (\Throwable $e) {
            Toastr::error($e->getMessage());
        }

        return back();
    }

    public function toggleReminder1h(Request $request): RedirectResponse
    {
        $enabled = $request->boolean('enabled') ? '1' : '0';
        \DB::table('business_settings')->updateOrInsert(
            ['key' => 'session_reminder_1h_enabled'],
            ['value' => $enabled, 'updated_at' => now()]
        );
        Toastr::success(translate('1-hour reminder setting updated'));

        return back();
    }

}
