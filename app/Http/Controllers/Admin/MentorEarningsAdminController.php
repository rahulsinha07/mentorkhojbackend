<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\MentorLogic;
use App\Http\Controllers\Controller;
use App\Model\Invoice\InvoiceSetting;
use App\Model\Mentor\MentorEarning;
use App\Model\Mentor\MentorSessionCredit;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

class MentorEarningsAdminController extends Controller
{
    public function index(): View
    {
        $gstRate = (float) (InvoiceSetting::instance()->default_tax_rate ?? 18);

        // --- "Paid out to mentors" stays strictly realized/completion-based:
        // a mentor can only actually be paid for sessions already delivered,
        // regardless of how much of their pack's funds have been collected.
        $realizedTotals = MentorEarning::query()
            ->selectRaw('COALESCE(SUM(net), 0) as net')
            ->first();
        $mentorPayout = round((float) $realizedTotals->net, 2);

        // --- Revenue / platform fee are cash-basis: money is "revenue" the
        // moment it's collected, not only once a session is delivered.
        // Direct (non-pack) bookings already work this way today — their
        // MentorEarning row is created at payment time (see
        // MentorBookingLogic::createBooking/syncFromLegacyOrder). Session
        // packs used to only count revenue per completed session
        // (consumeOnComplete), which under-reported collected funds — fold
        // in the full pack amount the moment its payment is confirmed
        // instead, consistent with how direct bookings are already treated.
        $directEarnings = MentorEarning::query()
            ->whereHas('booking', fn ($q) => $q->whereNull('session_credit_id'))
            ->get(['gross', 'fee', 'net', 'created_at']);

        $paidPacks = MentorSessionCredit::query()
            ->where('payment_status', 'paid')
            ->get(['id', 'fund_total', 'credits_total', 'credits_used', 'platform_fee_percent', 'last_granted_at', 'created_at']);

        $totalRevenue = 0.0;
        $platformEarning = 0.0;
        foreach ($directEarnings as $row) {
            $totalRevenue += (float) $row->gross;
            $platformEarning += (float) $row->fee;
        }
        foreach ($paidPacks as $credit) {
            $fee = round((float) $credit->fund_total * $credit->effectiveFeePercent() / 100, 2);
            $totalRevenue += (float) $credit->fund_total;
            $platformEarning += $fee;
        }
        $totalRevenue = round($totalRevenue, 2);
        $platformEarning = round($platformEarning, 2);
        // GST is charged on top of the session price paid by the student, not on the platform's commission.
        $gstOnEarning = round($totalRevenue * ($gstRate / 100), 2);

        $activePacksCount = $paidPacks->filter(fn ($c) => (int) $c->credits_used < (int) $c->credits_total)->count();
        $totalPacksCount = $paidPacks->count();
        $totalAmountAdded = round((float) $paidPacks->sum('fund_total'), 2);
        $totalSessionsAdded = (int) $paidPacks->sum('credits_total');
        $totalSessionsCompleted = (int) $paidPacks->sum('credits_used');
        $totalSessionsPending = max(0, $totalSessionsAdded - $totalSessionsCompleted);

        // --- Monthly breakdown: bucket revenue by when it was actually
        // collected — a direct booking's payment month, or a pack's most
        // recent grant/top-up month — not by session-completion month.
        $buckets = [];
        $ensureBucket = function (string $ym) use (&$buckets) {
            if (!isset($buckets[$ym])) {
                $buckets[$ym] = [
                    'sessions_added' => 0,
                    'sessions_completed' => 0,
                    'revenue' => 0.0,
                    'fee' => 0.0,
                    'net' => 0.0,
                ];
            }
        };
        foreach ($directEarnings as $row) {
            $ym = $row->created_at->format('Y-m');
            $ensureBucket($ym);
            $buckets[$ym]['sessions_added'] += 1;
            $buckets[$ym]['sessions_completed'] += 1;
            $buckets[$ym]['revenue'] += (float) $row->gross;
            $buckets[$ym]['fee'] += (float) $row->fee;
            $buckets[$ym]['net'] += (float) $row->net;
        }
        foreach ($paidPacks as $credit) {
            $when = $credit->last_granted_at ?? $credit->created_at;
            $ym = $when->format('Y-m');
            $ensureBucket($ym);
            $fee = round((float) $credit->fund_total * $credit->effectiveFeePercent() / 100, 2);
            $buckets[$ym]['sessions_added'] += (int) $credit->credits_total;
            $buckets[$ym]['sessions_completed'] += (int) $credit->credits_used;
            $buckets[$ym]['revenue'] += (float) $credit->fund_total;
            $buckets[$ym]['fee'] += $fee;
            $buckets[$ym]['net'] += ((float) $credit->fund_total - $fee);
        }

        $months = [];
        $revenueSeries = [];
        $earningSeries = [];
        $payoutSeries = [];
        $monthlyBreakdown = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $key = $date->format('Y-m');
            $row = $buckets[$key] ?? null;
            $label = $date->format('M Y');
            $months[] = $label;
            $revenue = $row ? round($row['revenue'], 2) : 0;
            $fee = $row ? round($row['fee'], 2) : 0;
            $net = $row ? round($row['net'], 2) : 0;
            $added = $row ? (int) $row['sessions_added'] : 0;
            $completed = $row ? (int) $row['sessions_completed'] : 0;
            $revenueSeries[] = $revenue;
            $earningSeries[] = $fee;
            $payoutSeries[] = $net;
            $monthlyBreakdown[] = [
                'label' => $label,
                'sessions_added' => $added,
                'sessions_completed' => $completed,
                'sessions_pending' => max(0, $added - $completed),
                'revenue' => $revenue,
                'fee' => $fee,
                'net' => $net,
            ];
        }

        $thisMonthKey = now()->format('Y-m');
        $thisMonthRow = $buckets[$thisMonthKey] ?? null;
        $thisMonthRevenue = $thisMonthRow ? round($thisMonthRow['revenue'], 2) : 0;
        $thisMonthEarning = $thisMonthRow ? round($thisMonthRow['fee'], 2) : 0;
        $thisMonthPayout = $thisMonthRow ? round($thisMonthRow['net'], 2) : 0;
        $thisMonthSessions = $thisMonthRow ? (int) $thisMonthRow['sessions_added'] : 0;
        $thisMonthGst = round($thisMonthRevenue * ($gstRate / 100), 2);

        return view('admin-views.session-packs.earnings', compact(
            'gstRate',
            'totalRevenue',
            'platformEarning',
            'mentorPayout',
            'gstOnEarning',
            'thisMonthRevenue',
            'thisMonthEarning',
            'thisMonthPayout',
            'thisMonthSessions',
            'thisMonthGst',
            'months',
            'revenueSeries',
            'earningSeries',
            'payoutSeries',
            'monthlyBreakdown',
            'activePacksCount',
            'totalPacksCount',
            'totalAmountAdded',
            'totalSessionsAdded',
            'totalSessionsCompleted',
            'totalSessionsPending'
        ));
    }
}
