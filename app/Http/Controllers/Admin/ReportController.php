<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Model\Branch;
use App\Model\BusinessSetting;
use App\Model\Order;
use App\Model\OrderDetail;
use Barryvdh\DomPDF\Facade as PDF;
use Box\Spout\Common\Exception\InvalidArgumentException;
use Box\Spout\Common\Exception\IOException;
use Box\Spout\Common\Exception\UnsupportedTypeException;
use Box\Spout\Writer\Exception\WriterNotOpenedException;
use Carbon\CarbonPeriod;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\View;
use Rap2hpoutre\FastExcel\FastExcel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private Branch $branch,
        private BusinessSetting $businessSetting,
        private Order $order,
        private OrderDetail $orderDetail
    ){}


    /**
     * @return Application|Factory|\Illuminate\Contracts\View\View
     */
    public function order_index(): Factory|\Illuminate\Contracts\View\View|Application
    {
        if (session()->has('from_date') == false) {
            session()->put('from_date', date('Y-m-01'));
            session()->put('to_date', date('Y-m-30'));
        }
        return view('admin-views.report.order-index');
    }


    /**
     * @return Application|Factory|\Illuminate\Contracts\View\View
     */
    public function earning_index(): Factory|\Illuminate\Contracts\View\View|Application
    {
        if (session()->has('from_date') == false) {
            session()->put('from_date', date('Y-m-01'));
            session()->put('to_date', date('Y-m-30'));
        }
        return view('admin-views.report.earning-index');
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function setDate(Request $request): \Illuminate\Http\RedirectResponse
    {
        $fromDate = Carbon::parse($request['from'])->startOfDay();
        $toDate = Carbon::parse($request['to'])->endOfDay();

        session()->put('from_date', $fromDate);
        session()->put('to_date', $toDate);
        return back();
    }

    /**
     * @param Request $request
     * @return Factory|\Illuminate\Contracts\View\View|Application
     */
    public function saleReportIndex(Request $request): \Illuminate\Contracts\View\View|Factory|Application
    {
        $queryParam = [];
        $branches = $this->branch->all();
        $branchId = $request['branch_id'];
        $startDate = $request['start_date'];
        $endDate = $request['end_date'];

        $orders = $this->order
            ->when((!is_null($startDate) && !is_null($endDate)), function ($query) use ($startDate, $endDate) {
                return $query->whereDate('created_at', '>=', $startDate)
                    ->whereDate('created_at', '<=', $endDate);
            })
            ->pluck('id')->toArray();

        if (!is_null($branchId) && $branchId != 'all') {
            $orders = $this->order->where(['branch_id' => $branchId])
                ->when((!is_null($startDate) && !is_null($endDate)), function ($query) use ($startDate, $endDate) {
                    return $query->whereDate('created_at', '>=', $startDate)
                        ->whereDate('created_at', '<=', $endDate);
                })
                ->pluck('id')->toArray();
        }

        $queryParam = ['branch_id' => $branchId, 'start_date' => $startDate,'end_date' => $endDate ];

        $orderDetails = $this->orderDetail->withCount(['order'])
            ->whereIn('order_id', $orders)
            ->orderBy('id', 'DESC')
            ->paginate(Helpers::getPagination())
            ->appends($queryParam);

        $data = [];
        $totalSold = 0;
        $totalQty = 0;
        foreach ($this->orderDetail->whereIn('order_id', $orders)->get() as $detail) {
            $price = $detail['price'] - $detail['discount_on_product'];
            $orderTotal = $price * $detail['quantity'];

            $product = json_decode($detail->product_details, true);

            $data[] = [
                'product_id' => $product['id'],
            ];
            $totalSold += $orderTotal;
            $totalQty += $detail['quantity'];
        }

        $totalOrder = count($data);

        return view('admin-views.report.sale-report', compact( 'orders', 'totalOrder', 'totalSold', 'totalQty', 'orderDetails', 'branches', 'branchId', 'startDate', 'endDate'));
    }

    /**
     * @return mixed
     */
    public function exportSaleReport(): mixed
    {
        $data = session('export_sale_data');
        $pdf = PDF::loadView('admin-views.report.partials._report', compact('data'));
        return $pdf->download('sale_report_'.rand(00001,99999) . '.pdf');
    }

    /**
     * @param Request $request
     * @return Application|Factory|\Illuminate\Contracts\View\View
     */
    public function expenseIndex(Request $request): Factory|\Illuminate\Contracts\View\View|Application
    {
        $search = $request['search'];
        $startDate = $request['start_date'];
        $endDate = $request['end_date'];
        $dateType = $request['date_type'] ?? 'this_year';
        $queryParam = ['search' => $search, 'date_type' => $dateType, 'start_date' => $startDate, 'end_date' => $endDate];

        $expenseCalculate = $this->order->with('coupon')
            ->where('order_status', 'delivered')
            ->where(function ($query){
                $query->whereNotIn('coupon_code', ['0', 'NULL'])
                    ->orWhere('free_delivery_amount', '>', 0)
                    ->orWhere('extra_discount', '>', 0);
            })
            ->when(($dateType == 'this_year'), function ($query) {
                return $query->whereYear('created_at', date('Y'));
            })
            ->when(($dateType == 'this_month'), function ($query) {
                return $query->whereMonth('created_at', date('m'))
                    ->whereYear('created_at', date('Y'));
            })
            ->when(($dateType == 'this_week'), function ($query) {
                return $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
            })
            ->when(($dateType == 'custom_date' && !is_null($startDate) && !is_null($endDate)), function ($query) use ($startDate, $endDate) {
                return $query->whereDate('created_at', '>=', $startDate)
                    ->whereDate('created_at', '<=', $endDate);
            })
            ->latest()
            ->get();


        $totalExpense = 0;
        $extraDiscount = 0;
        $freeDelivery = 0;
        $freeDeliveryOverAmount = 0;
        $couponDiscount = 0;
        if($expenseCalculate){
            foreach ($expenseCalculate as $calculate){
                $extraDiscount += $calculate->extra_discount;
                $freeDeliveryOverAmount += $calculate->free_delivery_amount;
                if(isset($calculate->coupon->coupon_type) && $calculate->coupon->coupon_type == 'free_delivery'){
                    $freeDelivery += $calculate->coupon_discount_amount;
                }else{
                    $couponDiscount += $calculate->coupon_discount_amount;
                }
            }
        }
        $freeDelivery += $freeDeliveryOverAmount;
        $totalExpense = $extraDiscount + $freeDelivery + $couponDiscount;

        $expenseTransactionChart = self::expenseTransactionChartFilter($request);

        $expenseTransactionsTable = $this->order->with('coupon')
            ->where('order_status', 'delivered')
            ->where(function ($query){
                $query->whereNotIn('coupon_code', ['0', 'NULL'])
                    ->orWhere('free_delivery_amount', '>', 0)
                    ->orWhere('extra_discount', '>', 0);
            })
            ->when($search, function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhere('coupon_code', 'like', "%{$search}%");
            })
            ->when(($dateType == 'this_year'), function ($query) {
                return $query->whereYear('created_at', date('Y'));
            })
            ->when(($dateType == 'this_month'), function ($query) {
                return $query->whereMonth('created_at', date('m'))
                    ->whereYear('created_at', date('Y'));
            })
            ->when(($dateType == 'this_week'), function ($query) {
                return $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
            })
            ->when(($dateType == 'custom_date' && !is_null($startDate) && !is_null($endDate)), function ($query) use ($startDate, $endDate) {
                return $query->whereDate('created_at', '>=', $startDate)
                    ->whereDate('created_at', '<=', $endDate);
            })
            ->latest()
            ->paginate(Helpers::getPagination())
            ->appends($queryParam);

        return view('admin-views.report.expense-report', compact('search', 'startDate', 'endDate', 'dateType', 'expenseTransactionsTable', 'totalExpense', 'freeDelivery', 'couponDiscount', 'extraDiscount', 'expenseTransactionChart'));
    }

    /**
     * @param $request
     * @return array[]|void
     */
    public function expenseTransactionChartFilter($request)
    {
        $from = $request['start_date'];
        $to = $request['end_date'];
        $dateType = $request['date_type'] ?? 'this_year';

        if ($dateType == 'this_year') {
            $number = 12;
            $default_inc = 1;
            $current_start_year = date('Y-01-01');
            $current_end_year = date('Y-12-31');
            $from_year = Carbon::parse($from)->format('Y');

            $this_year = self::expenseTransactionSameYear($request, $current_start_year, $current_end_year, $from_year, $number, $default_inc);
            return $this_year;

        } elseif ($dateType == 'this_month') { //this month table
            $current_month_start = date('Y-m-01');
            $current_month_end = date('Y-m-t');
            $inc = 1;
            $month = date('m');
            $number = date('d', strtotime($current_month_end));

            $this_month = self::expenseTransactionSameMonth($request, $current_month_start, $current_month_end, $month, $number, $inc);
            return $this_month;

        } elseif ($dateType == 'this_week') {
            $this_week = self::expenseTransactionThisWeek($request);
            return $this_week;

        } elseif ($dateType == 'custom_date' && !empty($from) && !empty($to)) {
            $start_date = Carbon::parse($from)->format('Y-m-d 00:00:00');
            $end_date = Carbon::parse($to)->format('Y-m-d 23:59:59');
            $from_year = Carbon::parse($from)->format('Y');
            $from_month = Carbon::parse($from)->format('m');
            $from_day = Carbon::parse($from)->format('d');
            $to_year = Carbon::parse($to)->format('Y');
            $to_month = Carbon::parse($to)->format('m');
            $to_day = Carbon::parse($to)->format('d');

            if ($from_year != $to_year) {
                $different_year = self::expenseTransactionDifferentYear($request, $start_date, $end_date, $from_year, $to_year);
                return $different_year;

            } elseif ($from_month != $to_month) {
                $same_year = self::expenseTransactionSameYear($request, $start_date, $end_date, $from_year, $to_month, $from_month);
                return $same_year;

            } elseif ($from_month == $to_month) {
                $same_month = self::expenseTransactionSameMonth($request, $start_date, $end_date, $from_month, $to_day, $from_day);
                return $same_month;
            }

        }
    }

    /**
     * @param $request
     * @param $start_date
     * @param $end_date
     * @param $from_year
     * @param $number
     * @param $default_inc
     * @return array[]
     */
    public function expenseTransactionSameYear($request, $start_date, $end_date, $from_year, $number, $default_inc): array
    {
        $orders = self::expenseChartCommonQuery($request)
            ->selectRaw('sum(coupon_discount_amount) as discount_amount, sum(extra_discount) as extra_discount, sum(free_delivery_amount) as free_delivery_amount,
                        YEAR(created_at) year, MONTH(created_at) month')
            ->groupBy(DB::raw("DATE_FORMAT(created_at, '%M')"))
            ->latest('created_at')
            ->get();

        for ($inc = $default_inc; $inc <= $number; $inc++) {
            $month = date("F", strtotime("2023-$inc-01"));
            $discount_amount[$month . '-' . $from_year] = 0;
            foreach ($orders as $match) {
                if ($match['month'] == $inc) {
                    $discount_amount[$month . '-' . $from_year] = $match['discount_amount'] + $match['extra_discount'] + $match['free_delivery_amount'];
                }
            }
        }

        return array(
            'discount_amount' => $discount_amount,
        );
    }

    /**
     * @param $request
     * @param $start_date
     * @param $end_date
     * @param $month_date
     * @param $number
     * @param $default_inc
     * @return array[]
     */
    public function expenseTransactionSameMonth($request, $start_date, $end_date, $month_date, $number, $default_inc): array
    {
        $month = date("F", strtotime("2023-$month_date-01"));
        $orders = self::expenseChartCommonQuery($request)
            ->selectRaw('sum(coupon_discount_amount) as discount_amount, sum(extra_discount) as extra_discount, sum(free_delivery_amount) as free_delivery_amount,
                        YEAR(created_at) year, MONTH(created_at) month, DAY(created_at) day')
            ->groupBy(DB::raw("DATE_FORMAT(created_at, '%D')"))
            ->latest('created_at')->get();

        for ($inc = $default_inc; $inc <= $number; $inc++) {
            $day = Carbon::createFromFormat('j', $inc)->format('jS');
            $discount_amount[$day . '-' . $month] = 0;
            foreach ($orders as $match) {
                if ($match['day'] == $inc) {
                    $discount_amount[$day . '-' . $month] = $match['discount_amount'] + $match['extra_discount'] + $match['free_delivery_amount'];
                }
            }
        }

        return array(
            'discount_amount' => $discount_amount,
        );
    }

    /**
     * @param $request
     * @return array[]
     */
    public function expenseTransactionThisWeek($request): array
    {
        $start_date = Carbon::now()->startOfWeek();
        $end_date = Carbon::now()->endOfWeek();

        $number = 6;
        $period = CarbonPeriod::create(Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek());
        $day_name = array();
        foreach ($period as $date) {
            $day_name[] = $date->format('l');
        }

        $orders = self::expenseChartCommonQuery($request)
            ->select(
                DB::raw('sum(coupon_discount_amount) as discount_amount, sum(extra_discount) as extra_discount, sum(free_delivery_amount) as free_delivery_amount'),
                DB::raw("(DATE_FORMAT(created_at, '%W')) as day")
            )
            ->groupBy(DB::raw("DATE_FORMAT(created_at, '%D')"))
            ->latest('created_at')->get();

        for ($inc = 0; $inc <= $number; $inc++) {
            $discount_amount[$day_name[$inc]] = 0;
            foreach ($orders as $match) {
                if ($match['day'] == $day_name[$inc]) {
                    $discount_amount[$day_name[$inc]] = $match['discount_amount'] + $match['extra_discount'] + $match['free_delivery_amount'];
                }
            }
        }

        return array(
            'discount_amount' => $discount_amount,
        );
    }

    /**
     * @param $request
     * @param $start_date
     * @param $end_date
     * @param $from_year
     * @param $to_year
     * @return array[]
     */
    public function expenseTransactionDifferentYear($request, $start_date, $end_date, $from_year, $to_year): array
    {
        $orders = self::expenseChartCommonQuery($request)
            ->selectRaw('sum(coupon_discount_amount) as discount_amount, sum(extra_discount) as extra_discount, sum(free_delivery_amount) as free_delivery_amount,
                        YEAR(created_at) year')
            ->groupBy(DB::raw("DATE_FORMAT(created_at, '%Y')"))
            ->latest('created_at')->get();

        for ($inc = $from_year; $inc <= $to_year; $inc++) {
            $discount_amount[$inc] = 0;
            foreach ($orders as $match) {
                if ($match['year'] == $inc) {
                    $discount_amount[$inc] = $match['discount_amount'] + $match['extra_discount'] + $match['free_delivery_amount'];
                }
            }
        }

        return array(
            'discount_amount' => $discount_amount,
        );
    }

    /**
     * @param $request
     * @return mixed
     */
    public function expenseChartCommonQuery($request){
        $from = $request['start_date'];
        $to = $request['end_date'];
        $dateType = $request['date_type'] ?? 'this_year';

        $order_query = $this->order->with('coupon')
            ->where('order_status', 'delivered')
            ->where(function ($query){
                $query->whereNotIn('coupon_code', ['0', 'NULL'])
                    ->orWhere('free_delivery_amount', '>', 0)
                    ->orWhere('extra_discount', '>', 0);
            });

        return self::dateWiseCommonFilter($order_query, $dateType, $from, $to);
    }

    /**
     * @param $query
     * @param $date_type
     * @param $from
     * @param $to
     * @return mixed
     */
    public function dateWiseCommonFilter($query, $date_type, $from, $to)
    {
        return $query->when(($date_type == 'this_year'), function ($query) {
            return $query->whereYear('created_at', date('Y'));
        })
            ->when(($date_type == 'this_month'), function ($query) {
                return $query->whereMonth('created_at', date('m'))
                    ->whereYear('created_at', date('Y'));
            })
            ->when(($date_type == 'this_week'), function ($query) {
                return $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
            })
            ->when(($date_type == 'custom_date' && !is_null($from) && !is_null($to)), function ($query) use ($from, $to) {
                return $query->whereDate('created_at', '>=', $from)
                    ->whereDate('created_at', '<=', $to);
            });
    }

    /**
     * @param Request $request
     * @return void
     */
    public function expenseSummaryPdf(Request $request): void
    {
        $companyPhone = $this->businessSetting->where('key', 'phone')->first()->value ?? '';
        $companyEmail = $this->businessSetting->where('key', 'email_address')->first()->value ?? '';
        $companyName = $this->businessSetting->where('key', 'restaurant_name')->first()->value ?? '';
        $companyLogo = $this->businessSetting->where('key', 'logo')->first()->value ?? '';

        $search = $request['search'];
        $from = $request['start_date'];
        $to = $request['end_date'];
        $dateType = $request['date_type'] ?? 'this_year';

        $duration = str_replace('_', ' ', $dateType);
        if ($dateType == 'custom_date') {
            $duration = 'From ' . $from . ' To ' . $to;
        }

        $expenseReport = $this->order->with('coupon')
            ->where('order_status', 'delivered')
            ->where(function ($query){
                $query->whereNotIn('coupon_code', ['0', 'NULL'])
                    ->orWhere('free_delivery_amount', '>', 0)
                    ->orWhere('extra_discount', '>', 0);
            });

        $expenseCalculate = self::dateWiseCommonFilter($expenseReport, $dateType, $from, $to)->get();

        $totalExpense = 0;
        $extraDiscount = 0;
        $freeDelivery = 0;
        $freeDeliveryOverAmount = 0;
        $couponDiscount = 0;
        if($expenseCalculate){
            foreach ($expenseCalculate as $calculate){
                $extraDiscount += $calculate->extra_discount;
                $freeDeliveryOverAmount += $calculate->free_delivery_amount;
                if(isset($calculate->coupon->coupon_type) && $calculate->coupon->coupon_type == 'free_delivery'){
                    $freeDelivery += $calculate->coupon_discount_amount;
                }else{
                    $couponDiscount += $calculate->coupon_discount_amount;
                }
            }
        }
        $freeDelivery += $freeDeliveryOverAmount;
        $totalExpense = $extraDiscount + $freeDelivery + $couponDiscount;

        $data = [
            'total_expense' => $totalExpense,
            'free_delivery' => $freeDelivery,
            'coupon_discount' => $couponDiscount,
            'extra_discount' => $extraDiscount,
            'company_phone' => $companyPhone,
            'company_name' => $companyName,
            'company_email' => $companyEmail,
            'company_logo' => $companyLogo,
            'duration' => $duration,
        ];

        $mpdfView = View::make('admin-views.report.expense-summary-pdf', compact('data'));
        Helpers::gen_mpdf($mpdfView, 'expense-summary-report-', $dateType);

    }

    /**
     * @param Request $request
     * @return StreamedResponse|string
     * @throws IOException
     * @throws InvalidArgumentException
     * @throws UnsupportedTypeException
     * @throws WriterNotOpenedException
     */
    public function expenseExportExcel(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse|string
    {
        $search = $request['search'];
        $from = $request['start_date'];
        $to = $request['end_date'];
        $dateType = $request['date_type'] ?? 'this_year';

        $expenseReport = $this->order->with('coupon')
            ->where('order_status', 'delivered')
            ->where(function ($query){
                $query->whereNotIn('coupon_code', ['0', 'NULL'])
                    ->orWhere('free_delivery_amount', '>', 0)
                    ->orWhere('extra_discount', '>', 0);
            })
            ->when($search, function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhere('coupon_code', 'like', "%{$search}%");
            });

        $expenseList = self::dateWiseCommonFilter($expenseReport, $dateType, $from, $to)->latest()->get();

        $data = [];

        foreach ($expenseList as $transaction) {
            $expenseAmount = 0;
            if ($transaction->coupon_discount_amount > 0){
                $expenseAmount = $transaction->coupon_discount_amount;
            }elseif ($transaction->extra_discount > 0){
                $expenseAmount = $transaction->extra_discount;
            }elseif ($transaction->free_delivery_amount > 0){
                $expenseAmount = $transaction->free_delivery_amount;
            }

            if (isset($transaction->coupon->coupon_type)){
                $type = $transaction->coupon->coupon_type;
            }elseif ($transaction->free_delivery_amount > 0){
                $type = 'Free Delivery';
            }elseif ($transaction->extra_discount > 0){
                $type = 'Extra Discount';
            }else{
                $type = 'Coupon Deleted';
            }

            $data[] = [
                'Order Date' => date_format($transaction->created_at, 'd F Y'),
                'Order ID' => $transaction->id,
                'Expense Amount' => Helpers::set_symbol($expenseAmount),
                'Expense Type' => ucwords(str_replace('_', ' ', $type)),
            ];
        }
        return (new FastExcel($data))->download('expense_report.xlsx');

    }
}
