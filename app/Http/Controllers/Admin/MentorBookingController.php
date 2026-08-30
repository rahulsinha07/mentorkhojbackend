<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\CentralLogics\MentorBookingMailLogic;
use App\CentralLogics\SessionCreditLogic;
use App\Http\Controllers\Controller;
use App\Model\Mentor\MentorBooking;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MentorBookingController extends Controller
{
    private const STATUSES = ['requested', 'confirmed', 'completed', 'cancelled', 'refunded'];

    public function list(Request $request): View
    {
        $search = $request->get('search');
        $status = strtolower((string) $request->get('status', ''));
        if ($status && !in_array($status, self::STATUSES, true)) {
            $status = '';
        }

        $query = MentorBooking::with(['mentor.user', 'service', 'mentee'])->latest();

        if ($status === 'cancelled') {
            $query->whereIn('status', ['cancelled', 'refunded']);
        } elseif ($status) {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhereHas('mentor', fn ($m) => $m->where('display_name', 'like', "%{$search}%"))
                    ->orWhereHas('mentee', function ($u) use ($search) {
                        $u->where('f_name', 'like', "%{$search}%")
                            ->orWhere('l_name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        $bookings = $query->paginate(Helpers::getPagination())->appends($request->query());

        return view('admin-views.mentor.bookings.list', compact('bookings', 'search', 'status'));
    }

    public function show(int $id): View
    {
        $booking = MentorBooking::with(['mentor.user', 'service', 'mentee', 'earnings'])->findOrFail($id);

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
            SessionCreditLogic::markComplete($booking);
            Toastr::success(translate('Session marked complete'));
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
}
