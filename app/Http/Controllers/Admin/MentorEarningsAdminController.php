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

        $totals = MentorEarning::query()
            ->selectRaw('COALESCE(SUM(gross), 0) as gross, COALESCE(SUM(fee), 0) as fee, COALESCE(SUM(net), 0) as net')
            ->first();

        $totalRevenue = round((float) $totals->gross, 2);
        $platformEarning = round((float) $totals->fee, 2);
        $mentorPayout = round((float) $totals->net, 2);
        // GST is charged on top of the session price paid by the student, not on the platform's commission.
        $gstOnEarning = round($totalRevenue * ($gstRate / 100), 2);

        // "Funded" figures — money committed when a pack is purchased/assigned
        // (payment confirmed), independent of whether its sessions have been
        // completed yet. Distinct from the MentorEarning totals above, which
        // only realize once each individual session is marked complete.
        $packTotals = MentorSessionCredit::query()
            ->where('payment_status', 'paid')
            ->selectRaw('COUNT(*) as packs_count, COALESCE(SUM(fund_total), 0) as total_funded, COALESCE(SUM(credits_total), 0) as sessions_added, COALESCE(SUM(credits_used), 0) as sessions_completed')
            ->first();
        $activePacksCount = (int) MentorSessionCredit::query()
            ->where('payment_status', 'paid')
            ->whereColumn('credits_used', '<', 'credits_total')
            ->count();
        $totalPacksCount = (int) $packTotals->packs_count;
        $totalAmountAdded = round((float) $packTotals->total_funded, 2);
        $totalSessionsAdded = (int) $packTotals->sessions_added;
        $totalSessionsCompleted = (int) $packTotals->sessions_completed;

        $monthRows = MentorEarning::query()
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, COUNT(*) as sessions, COALESCE(SUM(gross), 0) as gross, COALESCE(SUM(fee), 0) as fee, COALESCE(SUM(net), 0) as net")
            ->where('created_at', '>=', now()->subMonths(11)->startOfMonth())
            ->groupBy('ym')
            ->orderBy('ym')
            ->get()
            ->keyBy('ym');

        $months = [];
        $revenueSeries = [];
        $earningSeries = [];
        $payoutSeries = [];
        $monthlyBreakdown = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $key = $date->format('Y-m');
            $row = $monthRows->get($key);
            $label = $date->format('M Y');
            $months[] = $label;
            $revenue = $row ? round((float) $row->gross, 2) : 0;
            $fee = $row ? round((float) $row->fee, 2) : 0;
            $net = $row ? round((float) $row->net, 2) : 0;
            $sessions = $row ? (int) $row->sessions : 0;
            $revenueSeries[] = $revenue;
            $earningSeries[] = $fee;
            $payoutSeries[] = $net;
            $monthlyBreakdown[] = [
                'label' => $label,
                'sessions' => $sessions,
                'revenue' => $revenue,
                'fee' => $fee,
                'net' => $net,
            ];
        }

        $thisMonthKey = now()->format('Y-m');
        $thisMonthRow = $monthRows->get($thisMonthKey);
        $thisMonthRevenue = $thisMonthRow ? round((float) $thisMonthRow->gross, 2) : 0;
        $thisMonthEarning = $thisMonthRow ? round((float) $thisMonthRow->fee, 2) : 0;
        $thisMonthPayout = $thisMonthRow ? round((float) $thisMonthRow->net, 2) : 0;
        $thisMonthSessions = $thisMonthRow ? (int) $thisMonthRow->sessions : 0;
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
            'totalSessionsCompleted'
        ));
    }
}
