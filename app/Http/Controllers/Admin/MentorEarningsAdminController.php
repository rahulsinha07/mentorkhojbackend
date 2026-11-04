<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\MentorLogic;
use App\Http\Controllers\Controller;
use App\Model\Invoice\InvoiceSetting;
use App\Model\Mentor\MentorEarning;
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

        $monthRows = MentorEarning::query()
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, COALESCE(SUM(gross), 0) as gross, COALESCE(SUM(fee), 0) as fee, COALESCE(SUM(net), 0) as net")
            ->where('created_at', '>=', now()->subMonths(11)->startOfMonth())
            ->groupBy('ym')
            ->orderBy('ym')
            ->get()
            ->keyBy('ym');

        $months = [];
        $revenueSeries = [];
        $earningSeries = [];
        $payoutSeries = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $key = $date->format('Y-m');
            $row = $monthRows->get($key);
            $months[] = $date->format('M Y');
            $revenueSeries[] = $row ? round((float) $row->gross, 2) : 0;
            $earningSeries[] = $row ? round((float) $row->fee, 2) : 0;
            $payoutSeries[] = $row ? round((float) $row->net, 2) : 0;
        }

        $thisMonthKey = now()->format('Y-m');
        $thisMonthRow = $monthRows->get($thisMonthKey);
        $thisMonthRevenue = $thisMonthRow ? round((float) $thisMonthRow->gross, 2) : 0;
        $thisMonthEarning = $thisMonthRow ? round((float) $thisMonthRow->fee, 2) : 0;
        $thisMonthGst = round($thisMonthRevenue * ($gstRate / 100), 2);

        return view('admin-views.session-packs.earnings', compact(
            'gstRate',
            'totalRevenue',
            'platformEarning',
            'mentorPayout',
            'gstOnEarning',
            'thisMonthRevenue',
            'thisMonthEarning',
            'thisMonthGst',
            'months',
            'revenueSeries',
            'earningSeries',
            'payoutSeries'
        ));
    }
}
