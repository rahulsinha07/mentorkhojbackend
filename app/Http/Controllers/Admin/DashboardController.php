<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Model\Admin;
use App\Model\DemoBooking;
use App\Model\Mentor\MentorBooking;
use Carbon\CarbonPeriod;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct(
        private Admin $admin,
        private MentorBooking $mentorBooking,
        private DemoBooking $demoBooking
    ) {}

    /**
     * @param $id
     * @return string
     */
    public function fcm($id): string
    {
        $adminFcmToken = $this->admin->find(auth('admin')->id())->fcm_token;
        $data = [
            'title' => 'New auto generate message arrived from admin dashboard',
            'description' => $id,
            'order_id' => '',
            'image' => '',
            'type' => 'order'
        ];

        try {
            Helpers::send_push_notif_to_device($adminFcmToken, $data);
            return "Notification sent to admin";
        } catch (\Exception $exception) {
            return "Notification send failed";
        }
    }

    /**
     * @return Factory|View|Application
     */
    public function dashboard(): View|Factory|Application
    {
        $data = self::orderStatsData();

        $data['requested_count'] = $this->mentorBooking->where('status', 'requested')->count();
        $data['confirmed_count'] = $this->mentorBooking->where('status', 'confirmed')->count();
        $data['completed_count'] = $this->mentorBooking->where('status', 'completed')->count();
        $data['cancelled_count'] = $this->mentorBooking->whereIn('status', ['cancelled', 'refunded'])->count();

        $data['recent_bookings'] = $this->mentorBooking
            ->with(['mentor', 'service', 'mentee'])
            ->latest()
            ->take(5)
            ->get();

        $data['top_mentors'] = $this->mentorBooking
            ->with(['mentor'])
            ->select('mentor_id', DB::raw('COUNT(*) as count'))
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->groupBy('mentor_id')
            ->orderByDesc('count')
            ->take(6)
            ->get();

        $data['recent_demos'] = $this->demoBooking->latest()->take(6)->get();

        $data['top_mentees'] = $this->mentorBooking
            ->with(['mentee'])
            ->select('mentee_user_id', DB::raw('COUNT(*) as count'))
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->whereNotNull('mentee_user_id')
            ->groupBy('mentee_user_id')
            ->orderByDesc('count')
            ->take(6)
            ->get();

        $from = Carbon::now()->startOfYear()->format('Y-m-d');
        $to = Carbon::now()->endOfYear()->format('Y-m-d');

        $earning = $this->fillMonthlySeries(
            $this->paidEarningQuery()
                ->select(
                    DB::raw('IFNULL(SUM(amount + tax_amount), 0) as sums'),
                    DB::raw('YEAR(created_at) year, MONTH(created_at) month')
                )
                ->whereBetween('created_at', [$from, $to])
                ->groupBy('year', 'month')
                ->get()
                ->toArray(),
            'sums'
        );

        $orderStatisticsChart = $this->fillMonthlySeries(
            $this->mentorBooking->newQuery()
                ->select(
                    DB::raw('COUNT(id) as total'),
                    DB::raw('YEAR(created_at) year, MONTH(created_at) month')
                )
                ->whereBetween('created_at', [$from, $to])
                ->groupBy('year', 'month')
                ->get()
                ->toArray(),
            'total'
        );

        $demoStatisticsChart = $this->fillMonthlySeries(
            $this->demoBooking->newQuery()
                ->select(
                    DB::raw('COUNT(id) as total'),
                    DB::raw('YEAR(created_at) year, MONTH(created_at) month')
                )
                ->whereBetween('created_at', [$from, $to])
                ->groupBy('year', 'month')
                ->get()
                ->toArray(),
            'total'
        );

        return view('admin-views.dashboard', compact('data', 'earning', 'orderStatisticsChart', 'demoStatisticsChart'));
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function orderStats(Request $request): JsonResponse
    {
        session()->put('statistics_type', $request['statistics_type']);
        $data = self::orderStatsData();

        return response()->json([
            'view' => view('admin-views.partials._dashboard-order-stats', compact('data'))->render()
        ], 200);
    }

    /**
     * @return array
     */
    public function orderStatsData(): array
    {
        $today = session()->has('statistics_type') && session('statistics_type') == 'today' ? 1 : 0;
        $thisMonth = session()->has('statistics_type') && session('statistics_type') == 'this_month' ? 1 : 0;

        $applyPeriod = function (Builder $query) use ($today, $thisMonth) {
            return $query
                ->when($today, fn ($q) => $q->whereDate('created_at', Carbon::today()))
                ->when($thisMonth, fn ($q) => $q->whereMonth('created_at', Carbon::now())->whereYear('created_at', Carbon::now()->year));
        };

        $mentorCount = function (array $statuses) use ($applyPeriod) {
            $query = $this->mentorBooking->newQuery()->whereIn('status', $statuses);
            return $applyPeriod($query)->count();
        };

        $demoCount = function (string $status) use ($applyPeriod) {
            $query = $this->demoBooking->newQuery()->where('status', $status);
            return $applyPeriod($query)->count();
        };

        return [
            'mentor_requested' => $mentorCount(['requested']),
            'mentor_confirmed' => $mentorCount(['confirmed']),
            'mentor_completed' => $mentorCount(['completed']),
            'mentor_cancelled' => $mentorCount(['cancelled', 'refunded']),
            'demo_new' => $demoCount('new'),
            'demo_contacted' => $demoCount('contacted'),
            'demo_scheduled' => $demoCount('scheduled'),
            'demo_converted' => $demoCount('converted'),
            'mentor_all' => $applyPeriod($this->mentorBooking->newQuery())->count(),
            'demo_all' => $applyPeriod($this->demoBooking->newQuery())->count(),
        ];
    }

    /**
     * Filter booking statistics in week, month, year by ajax
     */
    public function getOrderStatistics(Request $request): JsonResponse
    {
        $dateType = $request->type;
        $mentorData = [];
        $demoData = [];
        $key_range = [];

        if ($dateType == 'yearOrder') {
            $from = Carbon::now()->startOfYear()->format('Y-m-d');
            $to = Carbon::now()->endOfYear()->format('Y-m-d');
            $key_range = ['Jan', 'Feb', 'Mar', 'April', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

            $mentorData = $this->fillMonthlySeries(
                $this->mentorBooking->newQuery()
                    ->select(DB::raw('COUNT(id) as total'), DB::raw('YEAR(created_at) year, MONTH(created_at) month'))
                    ->whereBetween('created_at', [$from, $to])
                    ->groupBy('year', 'month')
                    ->get()
                    ->toArray(),
                'total'
            );
            $demoData = $this->fillMonthlySeries(
                $this->demoBooking->newQuery()
                    ->select(DB::raw('COUNT(id) as total'), DB::raw('YEAR(created_at) year, MONTH(created_at) month'))
                    ->whereBetween('created_at', [$from, $to])
                    ->groupBy('year', 'month')
                    ->get()
                    ->toArray(),
                'total'
            );
        } elseif ($dateType == 'MonthOrder') {
            $from = date('Y-m-01');
            $to = date('Y-m-t');
            $number = (int) date('d', strtotime($to));
            $key_range = range(1, $number);

            $mentorData = $this->fillDailySeries(
                $this->mentorBooking->newQuery()
                    ->select(DB::raw('COUNT(id) as total'), DB::raw('DAY(created_at) day'))
                    ->whereBetween('created_at', [$from, $to])
                    ->groupBy('day')
                    ->get()
                    ->toArray(),
                $number,
                'total'
            );
            $demoData = $this->fillDailySeries(
                $this->demoBooking->newQuery()
                    ->select(DB::raw('COUNT(id) as total'), DB::raw('DAY(created_at) day'))
                    ->whereBetween('created_at', [$from, $to])
                    ->groupBy('day')
                    ->get()
                    ->toArray(),
                $number,
                'total'
            );
        } elseif ($dateType == 'WeekOrder') {
            Carbon::setWeekStartsAt(Carbon::SUNDAY);
            Carbon::setWeekEndsAt(Carbon::SATURDAY);

            $from = Carbon::now()->startOfWeek()->format('Y-m-d 00:00:00');
            $to = Carbon::now()->endOfWeek()->format('Y-m-d 23:59:59');
            $key_range = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

            $dayRange = $this->weekDayRange($from, $to);
            $mentorData = $this->fillWeekSeries(
                $this->mentorBooking->newQuery()
                    ->select(DB::raw('COUNT(id) as total'), DB::raw('DAY(created_at) day'))
                    ->whereBetween('created_at', [$from, $to])
                    ->groupBy('day')
                    ->pluck('total', 'day')
                    ->toArray(),
                $dayRange
            );
            $demoData = $this->fillWeekSeries(
                $this->demoBooking->newQuery()
                    ->select(DB::raw('COUNT(id) as total'), DB::raw('DAY(created_at) day'))
                    ->whereBetween('created_at', [$from, $to])
                    ->groupBy('day')
                    ->pluck('total', 'day')
                    ->toArray(),
                $dayRange
            );
        }

        return response()->json([
            'orders_label' => $key_range,
            'orders' => array_values($mentorData),
            'demos' => array_values($demoData),
        ]);
    }

    /**
     * Filter earning statistics in week, month, year by ajax
     */
    public function getEarningStatistics(Request $request): JsonResponse
    {
        $dateType = $request->type;
        $earning_data = [];
        $key_range = [];

        if ($dateType == 'yearEarn') {
            $from = Carbon::now()->startOfYear()->format('Y-m-d');
            $to = Carbon::now()->endOfYear()->format('Y-m-d');
            $key_range = ['Jan', 'Feb', 'Mar', 'April', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

            $earning_data = $this->fillMonthlySeries(
                $this->paidEarningQuery()
                    ->select(
                        DB::raw('IFNULL(SUM(amount + tax_amount), 0) as sums'),
                        DB::raw('YEAR(created_at) year, MONTH(created_at) month')
                    )
                    ->whereBetween('created_at', [$from, $to])
                    ->groupBy('year', 'month')
                    ->get()
                    ->toArray(),
                'sums'
            );
        } elseif ($dateType == 'MonthEarn') {
            $from = date('Y-m-01');
            $to = date('Y-m-t');
            $number = (int) date('d', strtotime($to));
            $key_range = range(1, $number);

            $earning_data = $this->fillDailySeries(
                $this->paidEarningQuery()
                    ->select(
                        DB::raw('IFNULL(SUM(amount + tax_amount), 0) as sums'),
                        DB::raw('DAY(created_at) day')
                    )
                    ->whereBetween('created_at', [$from, $to])
                    ->groupBy('day')
                    ->get()
                    ->toArray(),
                $number,
                'sums'
            );
        } elseif ($dateType == 'WeekEarn') {
            Carbon::setWeekStartsAt(Carbon::SUNDAY);
            Carbon::setWeekEndsAt(Carbon::SATURDAY);

            $from = Carbon::now()->startOfWeek()->format('Y-m-d 00:00:00');
            $to = Carbon::now()->endOfWeek()->format('Y-m-d 23:59:59');
            $key_range = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

            $dayRange = $this->weekDayRange($from, $to);
            $earning_data = $this->fillWeekSeries(
                $this->paidEarningQuery()
                    ->select(
                        DB::raw('IFNULL(SUM(amount + tax_amount), 0) as sums'),
                        DB::raw('DAY(created_at) day')
                    )
                    ->whereBetween('created_at', [$from, $to])
                    ->groupBy('day')
                    ->pluck('sums', 'day')
                    ->toArray(),
                $dayRange
            );
        }

        return response()->json([
            'earning_label' => $key_range,
            'earning' => array_values($earning_data),
        ]);
    }

    private function paidEarningQuery(): Builder
    {
        return $this->mentorBooking->newQuery()
            ->where('payment_status', 'paid')
            ->whereNotIn('status', ['cancelled', 'refunded']);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, float|int>
     */
    private function fillMonthlySeries(array $rows, string $valueKey): array
    {
        $series = [];
        for ($inc = 1; $inc <= 12; $inc++) {
            $series[$inc] = 0;
            foreach ($rows as $match) {
                if ((int) $match['month'] === $inc) {
                    $series[$inc] = $match[$valueKey];
                }
            }
        }

        return $series;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, float|int>
     */
    private function fillDailySeries(array $rows, int $number, string $valueKey): array
    {
        $series = [];
        for ($inc = 1; $inc <= $number; $inc++) {
            $series[$inc] = 0;
            foreach ($rows as $match) {
                if ((int) $match['day'] === $inc) {
                    $series[$inc] = $match[$valueKey];
                }
            }
        }

        return $series;
    }

    /**
     * @return array<int, int>
     */
    private function weekDayRange(string $from, string $to): array
    {
        $day_range = [];
        foreach (CarbonPeriod::create($from, $to)->toArray() as $date) {
            $day_range[] = $date->format('d');
        }
        $day_range = array_flip($day_range);
        $day_range_keys = array_keys($day_range);
        $day_range_values = array_values($day_range);
        $day_range_intKeys = array_map('intval', $day_range_keys);

        return array_combine($day_range_intKeys, $day_range_values) ?: [];
    }

    /**
     * @param array<int, float|int> $valuesByDay
     * @param array<int, int> $dayRange
     * @return array<int, float|int>
     */
    private function fillWeekSeries(array $valuesByDay, array $dayRange): array
    {
        $series = [];
        foreach ($dayRange as $day => $value) {
            $series[$day] = 0;
        }
        foreach ($valuesByDay as $day => $amount) {
            if (array_key_exists($day, $series)) {
                $series[$day] = $amount;
            }
        }

        return $series;
    }
}
