<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\CentralLogics\SessionCreditLogic;
use App\Http\Controllers\Controller;
use App\Model\Mentor\MentorSessionCredit;
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

        $query = MentorSessionCredit::with(['mentor', 'mentee']);

        if (in_array($paymentFilter, ['paid', 'pending'], true)) {
            $query->where('payment_status', $paymentFilter);
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

        // Most recently assigned pack first (last_granted_at is only ever
        // touched by SessionCreditLogic::grant(), unlike updated_at which
        // also moves on payment toggles and session completions).
        $query->orderByRaw('COALESCE(last_granted_at, created_at) DESC');

        $packs = $query->paginate(Helpers::getPagination())->appends($request->query());

        return view('admin-views.session-packs.index', compact('packs', 'search', 'paymentFilter'));
    }

    public function show(int $id): View
    {
        $credit = MentorSessionCredit::with(['mentor', 'mentee'])->findOrFail($id);

        $bookings = $credit->bookings()
            ->with(['service'])
            ->orderByRaw('scheduled_at IS NULL')
            ->orderBy('scheduled_at')
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
