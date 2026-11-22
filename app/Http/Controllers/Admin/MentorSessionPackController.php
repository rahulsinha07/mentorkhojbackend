<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\CentralLogics\SessionCreditLogic;
use App\Http\Controllers\Controller;
use App\Model\Mentor\Mentor;
use App\Model\Mentor\MentorSessionCredit;
use App\User;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MentorSessionPackController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->get('search', ''));
        $paymentFilter = (string) $request->get('payment', '');
        $mentorFilter = (string) $request->get('mentor_id', '');
        $menteeFilter = (string) $request->get('mentee_user_id', '');
        $statusFilter = (string) $request->get('status', '');
        $sort = (string) $request->get('sort', 'desc') === 'asc' ? 'asc' : 'desc';

        $query = MentorSessionCredit::with(['mentor', 'mentee']);

        if (in_array($paymentFilter, ['paid', 'pending'], true)) {
            $query->where('payment_status', $paymentFilter);
        }

        if ($mentorFilter !== '') {
            $query->where('mentor_id', $mentorFilter);
        }

        if ($menteeFilter !== '') {
            $query->where('mentee_user_id', $menteeFilter);
        }

        if ($statusFilter === 'active') {
            $query->whereColumn('credits_used', '<', 'credits_total');
        } elseif ($statusFilter === 'completed') {
            $query->whereColumn('credits_used', '>=', 'credits_total');
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhereHas('mentor', fn ($m) => $m->where('display_name', 'like', "%{$search}%"))
                    ->orWhereHas('mentee', function ($u) use ($search) {
                        $u->where('f_name', 'like', "%{$search}%")
                            ->orWhere('l_name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        // Most recently assigned pack first by default (last_granted_at is only
        // ever touched by SessionCreditLogic::grant(), unlike updated_at which
        // also moves on payment toggles and session completions). The "sort"
        // filter lets the admin flip to oldest-first without changing the
        // default.
        $query->orderByRaw('COALESCE(last_granted_at, created_at) ' . ($sort === 'asc' ? 'ASC' : 'DESC'));

        $packs = $query->paginate(Helpers::getPagination())->appends($request->query());

        // Only mentors/students who actually have a pack — keeps the filter
        // dropdowns relevant instead of listing the whole platform.
        $mentorOptions = Mentor::whereIn('id', MentorSessionCredit::select('mentor_id')->distinct())
            ->orderBy('display_name')
            ->get(['id', 'display_name']);
        $menteeOptions = User::whereIn('id', MentorSessionCredit::select('mentee_user_id')->distinct())
            ->orderBy('f_name')
            ->get(['id', 'f_name', 'l_name']);

        return view('admin-views.session-packs.index', compact(
            'packs', 'search', 'paymentFilter', 'mentorFilter', 'menteeFilter', 'statusFilter', 'sort',
            'mentorOptions', 'menteeOptions'
        ));
    }

    public function show(int $id): View
    {
        $credit = MentorSessionCredit::with(['mentor.settings', 'mentee'])->findOrFail($id);

        $bookings = $credit->bookings()
            ->with(['service'])
            ->orderByRaw('session_number IS NULL')
            ->orderBy('session_number')
            ->orderBy('id')
            ->get();

        return view('admin-views.session-packs.show', compact('credit', 'bookings'));
    }

    public function toggleComplete(int $bookingId): RedirectResponse
    {
        $booking = \App\Model\Mentor\MentorBooking::findOrFail($bookingId);

        try {
            if ((string) $booking->status === 'completed') {
                SessionCreditLogic::unmarkComplete($booking);
                Toastr::success(translate('Session marked incomplete'));
            } else {
                SessionCreditLogic::markComplete($booking, true);
                Toastr::success(translate('Session marked complete'));
            }
        } catch (\Throwable $e) {
            Toastr::error($e->getMessage());
        }

        return back();
    }

    public function toggleMentorPayout(Request $request, int $bookingId): RedirectResponse
    {
        $booking = \App\Model\Mentor\MentorBooking::findOrFail($bookingId);

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

    public function togglePayment(int $id): RedirectResponse
    {
        $credit = MentorSessionCredit::findOrFail($id);

        if ($credit->isPaymentConfirmed()) {
            $credit->payment_status = 'pending';
            $credit->payment_confirmed_at = null;
            $credit->payment_confirmed_by_admin_id = null;
            $credit->save();
            Toastr::success(translate('Payment marked as pending'));
        } else {
            $credit->payment_status = 'paid';
            $credit->payment_confirmed_at = now();
            $credit->payment_confirmed_by_admin_id = auth('admin')->id();
            $credit->save();
            SessionCreditLogic::confirmPackEarnings($credit);
            Toastr::success(translate('Payment marked as received'));
        }

        return back();
    }
}
