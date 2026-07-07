<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Model\RecentSearch;
use App\Model\SearchedCategory;
use App\Model\SearchedData;
use App\Model\SearchedKeywordCount;
use App\Model\SearchedKeywordUser;
use App\Model\SearchedProduct;
use App\User;
use Box\Spout\Common\Exception\InvalidArgumentException;
use Box\Spout\Common\Exception\IOException;
use Box\Spout\Common\Exception\UnsupportedTypeException;
use Box\Spout\Writer\Exception\WriterNotOpenedException;
use Carbon\Carbon;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Rap2hpoutre\FastExcel\FastExcel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class
AnalyticController extends Controller
{
    public function __construct(
        private RecentSearch $recentSearch,
        private SearchedKeywordCount $searchedKeywordCount,
        private SearchedCategory $searchedCategory,
        private SearchedKeywordUser $searchedKeywordUser,
        private SearchedProduct $searchedProduct
    ){}

    /**
     * @param Request $request
     * @return Factory|View|Application
     */
    public function getKeywordSearch(Request $request): View|Factory|Application
    {
        Validator::make($request->all(), [
            'date_range' => 'in:today,all_time,this_week,last_week,this_month,last_month,last_15_days,this_year,last_year,last_6_month,this_year_1st_quarter,this_year_2nd_quarter,this_year_3rd_quarter,this_year_4th_quarter',
            'date_range_2' => 'in:today,all_time,this_week,last_week,this_month,last_month,last_15_days,this_year,last_year,last_6_month,this_year_1st_quarter,this_year_2nd_quarter,this_year_3rd_quarter,this_year_4th_quarter',
        ]);

        $search = $request['search'];
        $queryParams = ['search' => $search];
        if ($request->has('date_range')) {
            $queryParams['date_range'] = $request['date_range'];
        }
        else{
            $queryParams['date_range'] = 'today';
        }

        if ($request->has('date_range_2')) {
            $queryParams['date_range_2'] = $request['date_range_2'];
        }
        else{
            $queryParams['date_range_2'] = 'today';
        }

        //*** graph data - Trending Keywords ***
        $recentSearchCount = $this->recentSearch->with(['searched_user'])
            ->when($request->has('date_range'), function ($query) use ($request) {
                if ($request['date_range'] == 'today') {
                    $query->whereDate('created_at', Carbon::now()->toDateString());
                }
                elseif ($request['date_range'] == 'this_week') {
                    $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);

                } elseif ($request['date_range'] == 'last_week') {
                    $query->whereBetween('created_at', [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()]);

                } elseif ($request['date_range'] == 'this_month') {
                    $query->whereMonth('created_at', Carbon::now()->month);

                } elseif ($request['date_range'] == 'last_month') {
                    $query->whereMonth('created_at', Carbon::now()->subMonth()->month);

                } elseif ($request['date_range'] == 'last_15_days') {
                    $query->whereBetween('created_at', [Carbon::now()->subDay(15), Carbon::now()]);

                } elseif ($request['date_range'] == 'this_year') {
                    $query->whereYear('created_at', Carbon::now()->year);

                } elseif ($request['date_range'] == 'last_year') {
                    $query->whereYear('created_at', Carbon::now()->subYear()->year);

                } elseif ($request['date_range'] == 'last_6_month') {
                    $query->whereBetween('created_at', [Carbon::now()->subMonth(6), Carbon::now()]);

                } elseif ($request['date_range'] == 'this_year_1st_quarter') {
                    $query->whereBetween('created_at', [Carbon::now()->month(1)->startOfQuarter(), Carbon::now()->month(1)->endOfQuarter()]);

                } elseif ($request['date_range'] == 'this_year_2nd_quarter') {
                    $query->whereBetween('created_at', [Carbon::now()->month(4)->startOfQuarter(), Carbon::now()->month(4)->endOfQuarter()]);

                } elseif ($request['date_range'] == 'this_year_3rd_quarter') {
                    $query->whereBetween('created_at', [Carbon::now()->month(7)->startOfQuarter(), Carbon::now()->month(7)->endOfQuarter()]);

                } elseif ($request['date_range'] == 'this_year_4th_quarter') {
                    $query->whereBetween('created_at', [Carbon::now()->month(10)->startOfQuarter(), Carbon::now()->month(10)->endOfQuarter()]);
                }
            })
            ->select('keyword', DB::raw('count(*) as count'))
            ->groupBy('keyword')
            ->orderBy('count', 'desc')
            ->take(5)
            ->get();

        $graphTotal = 0;
        foreach ($recentSearchCount as $item) {
            $graphTotal += $item['count'];
        }

        $graphData = ['keyword' => [], 'count' => [], 'avg' => []];
        foreach ($recentSearchCount as $item) {
            $graphData['keyword'][] = Str::limit($item['keyword'], 13);
            $graphData['count'][] = $item['count'];
            $graphData['avg'][] = number_format($item['count']*100/$graphTotal ?? 0.0, 2);
        }

        $searchedKeywordCount = $this->searchedKeywordCount
            ->when($request->has('date_range_2'), function ($query) use ($request) {
                if ($request['date_range_2'] == 'today') {
                    $query->whereDate('created_at', Carbon::now()->toDateString());
                }
                elseif ($request['date_range_2'] == 'this_week') {
                    $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);

                } elseif ($request['date_range_2'] == 'last_week') {
                    $query->whereBetween('created_at', [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()]);

                } elseif ($request['date_range_2'] == 'this_month') {
                    $query->whereMonth('created_at', Carbon::now()->month);

                } elseif ($request['date_range_2'] == 'last_month') {
                    $query->whereMonth('created_at', Carbon::now()->subMonth()->month);

                } elseif ($request['date_range_2'] == 'last_15_days') {
                    $query->whereBetween('created_at', [Carbon::now()->subDay(15), Carbon::now()]);

                } elseif ($request['date_range_2'] == 'this_year') {
                    $query->whereYear('created_at', Carbon::now()->year);

                } elseif ($request['date_range_2'] == 'last_year') {
                    $query->whereYear('created_at', Carbon::now()->subYear()->year);

                } elseif ($request['date_range_2'] == 'last_6_month') {
                    $query->whereBetween('created_at', [Carbon::now()->subMonth(6), Carbon::now()]);

                } elseif ($request['date_range_2'] == 'this_year_1st_quarter') {
                    $query->whereBetween('created_at', [Carbon::now()->month(1)->startOfQuarter(), Carbon::now()->month(1)->endOfQuarter()]);

                } elseif ($request['date_range_2'] == 'this_year_2nd_quarter') {
                    $query->whereBetween('created_at', [Carbon::now()->month(4)->startOfQuarter(), Carbon::now()->month(4)->endOfQuarter()]);

                } elseif ($request['date_range_2'] == 'this_year_3rd_quarter') {
                    $query->whereBetween('created_at', [Carbon::now()->month(7)->startOfQuarter(), Carbon::now()->month(7)->endOfQuarter()]);

                } elseif ($request['date_range_2'] == 'this_year_4th_quarter') {
                    $query->whereBetween('created_at', [Carbon::now()->month(10)->startOfQuarter(), Carbon::now()->month(10)->endOfQuarter()]);
                }
            })
            ->sum('keyword_count');


        $categoryWiseVolumes = $this->searchedCategory
            ->when($request->has('date_range_2'), function ($query) use ($request) {
                if ($request['date_range_2'] == 'today') {
                    $query->whereDate('created_at', Carbon::now()->toDateString());
                }
                elseif ($request['date_range_2'] == 'this_week') {
                    $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);

                } elseif ($request['date_range_2'] == 'last_week') {
                    $query->whereBetween('created_at', [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()]);

                } elseif ($request['date_range_2'] == 'this_month') {
                    $query->whereMonth('created_at', Carbon::now()->month);

                } elseif ($request['date_range_2'] == 'last_month') {
                    $query->whereMonth('created_at', Carbon::now()->subMonth()->month);

                } elseif ($request['date_range_2'] == 'last_15_days') {
                    $query->whereBetween('created_at', [Carbon::now()->subDay(15), Carbon::now()]);

                } elseif ($request['date_range_2'] == 'this_year') {
                    $query->whereYear('created_at', Carbon::now()->year);

                } elseif ($request['date_range_2'] == 'last_year') {
                    $query->whereYear('created_at', Carbon::now()->subYear()->year);

                } elseif ($request['date_range_2'] == 'last_6_month') {
                    $query->whereBetween('created_at', [Carbon::now()->subMonth(6), Carbon::now()]);

                } elseif ($request['date_range_2'] == 'this_year_1st_quarter') {
                    $query->whereBetween('created_at', [Carbon::now()->month(1)->startOfQuarter(), Carbon::now()->month(1)->endOfQuarter()]);

                } elseif ($request['date_range_2'] == 'this_year_2nd_quarter') {
                    $query->whereBetween('created_at', [Carbon::now()->month(4)->startOfQuarter(), Carbon::now()->month(4)->endOfQuarter()]);

                } elseif ($request['date_range_2'] == 'this_year_3rd_quarter') {
                    $query->whereBetween('created_at', [Carbon::now()->month(7)->startOfQuarter(), Carbon::now()->month(7)->endOfQuarter()]);

                } elseif ($request['date_range_2'] == 'this_year_4th_quarter') {
                    $query->whereBetween('created_at', [Carbon::now()->month(10)->startOfQuarter(), Carbon::now()->month(10)->endOfQuarter()]);
                }
            })
            ->whereNotNull('category_id')
            ->with('category')
            ->groupBy('category_id')
            ->select('category_id', DB::raw('count(*) as count'))
            ->orderBy('count', 'desc')
            ->take(5)
            ->get();

        $total = 0;
        foreach ($categoryWiseVolumes as $item) {
            $total += $item['count'];
        }

        //*** table data ***

        $searchedTableData = $this->recentSearch
            ->with(['volume', 'searched_category', 'searched_category.category', 'searched_product' ])
            ->withCount('volume','searched_category', 'searched_product')
            ->when($request->has('search'), function ($query) use ($request) {
                $keys = explode(' ', $request['search']);
                foreach ($keys as $key) {
                    return $query->where('keyword', 'like', '%' . $key . '%');
                }
            })
            ->orderBy('volume_count', 'desc')
            ->paginate(Helpers::getPagination())
            ->appends($queryParams);

        return view('admin-views.analytics.keyword-search', compact('queryParams', 'graphData', 'search', 'categoryWiseVolumes', 'total', 'searchedKeywordCount', 'searchedTableData'));
    }

    /**
     * @param Request $request
     * @return Factory|View|Application
     * @throws ValidationException
     */
    public function getCustomerSearch(Request $request): View|Factory|Application
    {
        Validator::make($request->all(), [
            'date_range' => 'in:today,all_time,this_week,last_week,this_month,last_month,last_15_days,this_year,last_year,last_6_month,this_year_1st_quarter,this_year_2nd_quarter,this_year_3rd_quarter,this_year_4th_quarter',
            'date_range_2' => 'in:today,all_time,this_week,last_week,this_month,last_month,last_15_days,this_year,last_year,last_6_month,this_year_1st_quarter,this_year_2nd_quarter,this_year_3rd_quarter,this_year_4th_quarter',
        ])->validate();

        $search = $request['search'];
        $queryParams = ['search' => $search];
        if ($request->has('date_range')) {
            $queryParams['date_range'] = $request['date_range'];
        }
        else{
            $queryParams['date_range'] = 'today';
        }

        if ($request->has('date_range_2')) {
            $queryParams['date_range_2'] = $request['date_range_2'];
        }
        else{
            $queryParams['date_range_2'] = 'today';
        }

        //*** Graph Data **
        $topCustomer = $this->searchedKeywordUser->with(['customer'])
            ->when($request->has('date_range'), function ($query) use ($request) {
                if ($request['date_range'] == 'today') {
                    $query->whereDate('created_at', Carbon::now()->toDateString());
                }
                elseif ($request['date_range'] == 'this_week') {
                    $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);

                } elseif ($request['date_range'] == 'last_week') {
                    $query->whereBetween('created_at', [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()]);

                } elseif ($request['date_range'] == 'this_month') {
                    $query->whereMonth('created_at', Carbon::now()->month);

                } elseif ($request['date_range'] == 'last_month') {
                    $query->whereMonth('created_at', Carbon::now()->subMonth()->month);

                } elseif ($request['date_range'] == 'last_15_days') {
                    $query->whereBetween('created_at', [Carbon::now()->subDay(15), Carbon::now()]);

                } elseif ($request['date_range'] == 'this_year') {
                    $query->whereYear('created_at', Carbon::now()->year);

                } elseif ($request['date_range'] == 'last_year') {
                    $query->whereYear('created_at', Carbon::now()->subYear()->year);

                } elseif ($request['date_range'] == 'last_6_month') {
                    $query->whereBetween('created_at', [Carbon::now()->subMonth(6), Carbon::now()]);

                } elseif ($request['date_range'] == 'this_year_1st_quarter') {
                    $query->whereBetween('created_at', [Carbon::now()->month(1)->startOfQuarter(), Carbon::now()->month(1)->endOfQuarter()]);

                } elseif ($request['date_range'] == 'this_year_2nd_quarter') {
                    $query->whereBetween('created_at', [Carbon::now()->month(4)->startOfQuarter(), Carbon::now()->month(4)->endOfQuarter()]);

                } elseif ($request['date_range'] == 'this_year_3rd_quarter') {
                    $query->whereBetween('created_at', [Carbon::now()->month(7)->startOfQuarter(), Carbon::now()->month(7)->endOfQuarter()]);

                } elseif ($request['date_range'] == 'this_year_4th_quarter') {
                    $query->whereBetween('created_at', [Carbon::now()->month(10)->startOfQuarter(), Carbon::now()->month(10)->endOfQuarter()]);
                }
            })
            ->select(
                DB::raw('count(recent_search_id) as count'),
                DB::raw('user_id')
            )
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->take(5)
            ->get();


        $graphData = ['top_customers' => [], 'search_volume' => []];
        foreach ($topCustomer as $item) {
            $graphData['top_customers'][] = $item->customer ? $item->customer->f_name . ' ' . $item->customer->l_name :  '';
            $graphData['search_volume'][] = $item->count;
        }

        $topProducts = $this->searchedProduct
            ->when($request->has('date_range_2'), function ($query) use ($request) {
                if ($request['date_range_2'] == 'today') {
                    $query->whereDate('created_at', Carbon::now()->toDateString());
                }
                elseif ($request['date_range_2'] == 'this_week') {
                    $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);

                } elseif ($request['date_range_2'] == 'last_week') {
                    $query->whereBetween('created_at', [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()]);

                } elseif ($request['date_range_2'] == 'this_month') {
                    $query->whereMonth('created_at', Carbon::now()->month);

                } elseif ($request['date_range_2'] == 'last_month') {
                    $query->whereMonth('created_at', Carbon::now()->subMonth()->month);

                } elseif ($request['date_range_2'] == 'last_15_days') {
                    $query->whereBetween('created_at', [Carbon::now()->subDay(15), Carbon::now()]);

                } elseif ($request['date_range_2'] == 'this_year') {
                    $query->whereYear('created_at', Carbon::now()->year);

                } elseif ($request['date_range_2'] == 'last_year') {
                    $query->whereYear('created_at', Carbon::now()->subYear()->year);

                } elseif ($request['date_range_2'] == 'last_6_month') {
                    $query->whereBetween('created_at', [Carbon::now()->subMonth(6), Carbon::now()]);

                } elseif ($request['date_range_2'] == 'this_year_1st_quarter') {
                    $query->whereBetween('created_at', [Carbon::now()->month(1)->startOfQuarter(), Carbon::now()->month(1)->endOfQuarter()]);

                } elseif ($request['date_range_2'] == 'this_year_2nd_quarter') {
                    $query->whereBetween('created_at', [Carbon::now()->month(4)->startOfQuarter(), Carbon::now()->month(4)->endOfQuarter()]);

                } elseif ($request['date_range_2'] == 'this_year_3rd_quarter') {
                    $query->whereBetween('created_at', [Carbon::now()->month(7)->startOfQuarter(), Carbon::now()->month(7)->endOfQuarter()]);

                } elseif ($request['date_range_2'] == 'this_year_4th_quarter') {
                    $query->whereBetween('created_at', [Carbon::now()->month(10)->startOfQuarter(), Carbon::now()->month(10)->endOfQuarter()]);
                }
            })
            ->whereNotNull('product_id')
            ->with('product')
            ->groupBy('product_id')
            ->select('product_id', DB::raw('count(*) as count'))
            ->orderBy('count', 'desc')
            ->take(10)
            ->get();

        $total = 0;
        foreach ($topProducts as $top_product) {
            $total += $top_product['count'];
        }

        $customersData = $this->searchedKeywordUser->with(['customer', 'related_category', 'related_product', 'related_category.category'])
            ->when($request->has('search'), function ($query) use ($request) {
                $keys = explode(' ', $request['search']);
                $query->whereHas('customer', function ($query) use ($keys) {
                    $query->where(function ($query) use ($keys) {
                        foreach ($keys as $key) {
                            $query->orWhere('f_name', 'like', '%'.$key.'%')
                                ->orWhere('l_name', 'like', '%'.$key.'%');
                        }
                    });
                });
            })
            ->select('user_id', DB::raw('count(*) as search_volume'))
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->withCount('visited_products', 'orders', 'related_category', 'related_product')
            ->orderBy('search_volume', 'desc')
            ->paginate(Helpers::getPagination())->appends($queryParams);

        return view('admin-views.analytics.customer-search', compact('customersData','queryParams', 'topCustomer', 'topProducts', 'graphData', 'search', 'total'));
    }

    /**
     * @param Request $request
     * @return string|StreamedResponse
     * @throws IOException
     * @throws InvalidArgumentException
     * @throws UnsupportedTypeException
     * @throws WriterNotOpenedException
     */
    public function exportKeywordSearch(Request $request): StreamedResponse|string
    {
        $searchedList = $this->recentSearch
            ->with(['volume', 'searched_category', 'searched_category.category', 'searched_product' ])
            ->withCount('volume','searched_category', 'searched_product')
            ->latest()
            ->get();

        $data = [];
        foreach ($searchedList as $list) {
            $data[] = [
                'Keyword' => $list->keyword,
                'Search Volume' => $list->volume_count,
                'Related Categories' =>  $list->searched_category_count,
                'Related Products' =>  $list->searched_product_count,
            ];
        }
        return (new FastExcel($data))->download('keyword-search.xlsx');
    }

    /**
     * @param Request $request
     * @return string|StreamedResponse
     * @throws IOException
     * @throws InvalidArgumentException
     * @throws UnsupportedTypeException
     * @throws WriterNotOpenedException
     */
    public function exportCustomerSearch(Request $request): StreamedResponse|string
    {
        $customersList = $this->searchedKeywordUser->with(['customer', 'related_category', 'related_product', 'related_category.category'])
            ->select('user_id', DB::raw('count(*) as search_volume'))
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->withCount('visited_products', 'orders', 'related_category', 'related_product')
            ->latest()
            ->get();

        $data = [];
        foreach ($customersList as $list) {
            $data[] = [
                'Customer' => $list->customer ? $list->customer['f_name']." ".$list->customer['l_name'] : '',
                'Search Volume' => $list->search_volume,
                'Related Categories' =>  $list->related_category_count,
                'Related Products' =>  $list->related_product_count,
                'Times Products Visited' =>  $list->visited_products_count,
                'Total Orders' =>  $list->orders_count,
            ];
        }
        return (new FastExcel($data))->download('customer-search.xlsx');
    }
}
