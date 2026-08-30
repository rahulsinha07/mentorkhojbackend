<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Mail\Customer\OrderPlaced;
use App\Model\Branch;
use App\Model\Category;
use App\Model\CustomerAddress;
use App\Model\DemoBooking;
use App\Model\DeliveryMan;
use App\Model\Mentor\MentorBooking;
use App\Model\Order;
use App\Model\OrderDetail;
use App\Model\Product;
use App\Traits\HelperTrait;
use App\User;
use Box\Spout\Common\Exception\InvalidArgumentException;
use Box\Spout\Common\Exception\IOException;
use Box\Spout\Common\Exception\UnsupportedTypeException;
use Box\Spout\Writer\Exception\WriterNotOpenedException;
use Carbon\Carbon;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Pagination\LengthAwarePaginator;
use PHPUnit\Exception;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psy\VersionUpdater\SelfUpdate;
use Rap2hpoutre\FastExcel\FastExcel;
use Symfony\Component\HttpFoundation\StreamedResponse;
use function App\CentralLogics\translate;

class POSController extends Controller
{
    use HelperTrait;
    public function __construct(
        private Branch $branch,
        private Category $category,
        private DeliveryMan $delivery_man,
        private Order $order,
        private OrderDetail $order_detail,
        private Product $product,
        private User $user,
        private MentorBooking $mentorBooking,
        private DemoBooking $demoBooking
    ){}

    /**
     * @param Request $request
     * @return Factory|View|Application
     */
    public function index(Request $request): View|Factory|Application
    {
        $category = $request->query('category_id', 0);
        $categories = $this->category->where(['position' => 0])->active()->get();
        $keyword = $request->keyword;
        $key = explode(' ', $keyword);

        $products = $this->product
            ->when($request->has('category_id') && $request['category_id'] != 0, function ($query) use ($request) {
                $query->whereJsonContains('category_ids', [['id' => (string)$request['category_id']]]);
            })
            ->when($keyword, function ($query) use ($key) {
                return $query->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('name', 'like', "%{$value}%");
                    }
                });
            })
            ->active()->latest()->paginate(Helpers::getPagination());

        $branches = $this->branch->all();
        $users = $this->user->all();
        return view('admin-views.pos.index', compact('categories', 'products', 'category', 'keyword', 'branches', 'users'));
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function quickView(Request $request): JsonResponse
    {
        $product = $this->product->findOrFail($request->product_id);
        $discount = self::discountCalculation($product, $product['price']);

        return response()->json([
            'success' => 1,
            'view' => view('admin-views.pos._quick-view-data', compact('product', 'discount'))->render(),
        ]);
    }

    /**
     * @param Request $request
     * @return array
     */
    public function variantPrice(Request $request): array
    {
        $product = $this->product->find($request->id);
        $str = '';
        $price = 0;
        foreach (json_decode($product->choice_options) as $key => $choice) {
            if ($str != null) {
                $str .= '-' . str_replace(' ', '', $request[$choice->name]);
            } else {
                $str .= str_replace(' ', '', $request[$choice->name]);
            }
        }

        if ($str != null) {
            $count = count(json_decode($product->variations));
            for ($i = 0; $i < $count; $i++) {
                if (json_decode($product->variations)[$i]->type == $str) {
                    $price = json_decode($product->variations)[$i]->price;
                    $discount = self::discountCalculation($product, $price);
                    $price = $price - $discount;
                    $stock = json_decode($product->variations)[$i]->stock;
                }
            }
        } else {
            $price = $product->price;
            $discount = self::discountCalculation($product, $price);
            $price = $price - $discount;
            $stock = $product->total_stock;
        }

        return array('price' => Helpers::set_symbol(($price * $request->quantity)), 'stock' => $stock);
    }

    /**
     * @param $product
     * @param $price
     * @return float
     */
    public function discountCalculation($product, $price) : float
    {
        $categoryId = null;
        foreach (json_decode($product['category_ids'], true) as $cat) {
            if ($cat['position'] == 1) {
                $categoryId = ($cat['id']);
            }
        }

        $categoryDiscount = Helpers::category_discount_calculate($categoryId, $price);
        $productDiscount = Helpers::discount_calculate($product, $price);
        if ($categoryDiscount >= $price){
            $discount = $productDiscount;
        }else{
            $discount = max($categoryDiscount, $productDiscount);
        }
        return $discount;
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getCustomers(Request $request): \Illuminate\Http\JsonResponse
    {
        $key = explode(' ', $request['q']);
        $data = DB::table('users')
            ->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('f_name', 'like', "%{$value}%")
                        ->orWhere('l_name', 'like', "%{$value}%")
                        ->orWhere('phone', 'like', "%{$value}%");
                }
            })
            ->whereNotNull(['f_name', 'l_name', 'phone'])
            ->limit(8)
            ->get([DB::raw('id, CONCAT(f_name, " ", l_name, " (", phone ,")") as text')]);

        $data[] = (object)['id' => false, 'text' => translate('walk_in_customer')];

        return response()->json($data);
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function updateTax(Request $request): RedirectResponse
    {
        if ($request->tax < 0) {
            Toastr::error(translate('Tax_can_not_be_less_than_0_percent'));
            return back();
        } elseif ($request->tax > 100) {
            Toastr::error(translate('Tax_can_not_be_more_than_100_percent'));
            return back();
        }

        $cart = $request->session()->get('cart', collect([]));
        $cart['tax'] = $request->tax;
        $request->session()->put('cart', $cart);
        return back();
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function updateDiscount(Request $request): RedirectResponse
    {
        $total = session()->get('total');

        if ($request->type == 'percent' && $request->discount < 0) {
            Toastr::error(translate('Extra_discount_can_not_be_less_than_0_percent'));
            return back();
        } elseif ($request->type == 'percent' && $request->discount > 100) {
            Toastr::error(translate('Extra_discount_can_not_be_more_than_100_percent'));
            return back();
        }
        elseif ($request->type == 'amount' && $request->discount > $total) {
            Toastr::error(translate('Extra_discount_can_not_be_more_than_total_price'));
            return back();
        }

        $cart = $request->session()->get('cart', collect([]));

        $cart['extra_discount'] = $request->discount;
        $cart['extra_discount_type'] = $request->type;
        $request->session()->put('cart', $cart);

        Toastr::success(translate('Discount_applied'));
        return back();
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function updateQuantity(Request $request): \Illuminate\Http\JsonResponse
    {
        $cart = $request->session()->get('cart', collect([]));
        $cart = $cart->map(function ($object, $key) use ($request) {
            if ($key == $request->key) {
                $object['quantity'] = $request->quantity;
            }
            return $object;
        });
        $request->session()->put('cart', $cart);
        return response()->json([], 200);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function addToCart(Request $request): \Illuminate\Http\JsonResponse
    {
        $product = $this->product->find($request->id);

        $data = array();
        $data['id'] = $product->id;
        $str = '';
        $variations = [];
        $price = 0;

        if ($product['total_stock'] < $request['quantity']){
            return response()->json([
                'data' => 0
            ]);
        }

        foreach (json_decode($product->choice_options) as $key => $choice) {
            $data[$choice->name] = $request[$choice->name];
            $variations[$choice->title] = $request[$choice->name];
            if ($str != null) {
                $str .= '-' . str_replace(' ', '', $request[$choice->name]);
            } else {
                $str .= str_replace(' ', '', $request[$choice->name]);
            }
        }

        $data['variations'] = $variations;
        $data['variant'] = $str;
        if ($request->session()->has('cart')) {
            if (count($request->session()->get('cart')) > 0) {
                foreach ($request->session()->get('cart') as $key => $cartItem) {
                    if (is_array($cartItem) && $cartItem['id'] == $request['id'] && $cartItem['variant'] == $str) {
                        return response()->json([
                            'data' => 1
                        ]);
                    }
                }
            }
        }

        if ($str != null) {
            $count = count(json_decode($product->variations));
            for ($i = 0; $i < $count; $i++) {
                if (json_decode($product->variations)[$i]->type == $str) {
                    $price = json_decode($product->variations)[$i]->price;
                }
            }
        } else {
            $price = $product->price;
        }

        $taxOnProduct = Helpers::tax_calculate($product, $price);

        $discount = self::discountCalculation($product, $price);

        $data['quantity'] = $request['quantity'];
        $data['price'] = $price;
        $data['name'] = $product->name;
        $data['discount'] = $discount;
        $data['image'] = $product->image;

        if ($request->session()->has('cart')) {
            $cart = $request->session()->get('cart', collect([]));
            $cart->push($data);
        } else {
            $cart = collect([$data]);
            $request->session()->put('cart', $cart);
        }

        return response()->json([
            'data' => $data,
            'quantity' => $product->total_stock
        ]);
    }

    /**
     * @return Factory|View|Application
     */
    public function cartItems(): View|Factory|Application
    {
        return view('admin-views.pos._cart');
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function emptyCart(Request $request): \Illuminate\Http\JsonResponse
    {
        session()->forget('cart');
        session()->forget('customer_id');
        session()->forget('branch_id');
        session()->forget('address');
        session()->forget('order_type');
        return response()->json([], 200);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function removeFromCart(Request $request): \Illuminate\Http\JsonResponse
    {
        if ($request->session()->has('cart')) {
            $cart = $request->session()->get('cart', collect([]));
            $cart->forget($request->key);
            $request->session()->put('cart', $cart);
        }

        return response()->json([], 200);
    }

    /**
     * @param Request $request
     * @return Factory|View|Application
     */
    public function orderList(Request $request)
    {
        $search = trim((string) $request->get('search', ''));
        $type = strtolower((string) $request->get('type', 'all'));
        if (!in_array($type, ['all', 'mentor', 'demo'], true)) {
            $type = 'all';
        }

        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        $queryParam = array_filter([
            'search' => $search ?: null,
            'type' => $type !== 'all' ? $type : null,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ], fn ($value) => $value !== null && $value !== '');

        if ($type === 'mentor') {
            $bookings = $this->mentorBookingQuery($search, $startDate, $endDate)
                ->paginate(Helpers::getPagination())
                ->through(fn (MentorBooking $booking) => $this->normalizeMentorBookingRow($booking))
                ->appends($queryParam);
        } elseif ($type === 'demo') {
            $bookings = $this->demoBookingQuery($search, $startDate, $endDate)
                ->paginate(Helpers::getPagination())
                ->through(fn (DemoBooking $booking) => $this->normalizeDemoBookingRow($booking))
                ->appends($queryParam);
        } else {
            $bookings = $this->paginateUnifiedBookings($search, $startDate, $endDate, $request, $queryParam);
        }

        $counts = [
            'all' => $this->mentorBooking->count() + $this->demoBooking->count(),
            'mentor' => $this->mentorBooking->count(),
            'demo' => $this->demoBooking->count(),
        ];

        return view('admin-views.pos.order.list', compact(
            'bookings',
            'search',
            'type',
            'startDate',
            'endDate',
            'counts'
        ));
    }

    private function mentorBookingQuery(string $search, ?string $startDate, ?string $endDate)
    {
        return $this->mentorBooking->newQuery()
            ->with(['mentor', 'service', 'mentee'])
            ->when($startDate && $endDate, fn ($query) => $query->whereDate('created_at', '>=', $startDate)
                ->whereDate('created_at', '<=', $endDate))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('id', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhere('payment_status', 'like', "%{$search}%")
                        ->orWhereHas('mentor', fn ($m) => $m->where('display_name', 'like', "%{$search}%"))
                        ->orWhereHas('mentee', function ($u) use ($search) {
                            $u->where('f_name', 'like', "%{$search}%")
                                ->orWhere('l_name', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->latest();
    }

    private function demoBookingQuery(string $search, ?string $startDate, ?string $endDate)
    {
        return $this->demoBooking->newQuery()
            ->when($startDate && $endDate, fn ($query) => $query->whereDate('created_at', '>=', $startDate)
                ->whereDate('created_at', '<=', $endDate))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('booking_ref', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%");
                });
            })
            ->latest();
    }

    private function paginateUnifiedBookings(
        string $search,
        ?string $startDate,
        ?string $endDate,
        Request $request,
        array $queryParam
    ): LengthAwarePaginator {
        $mentorRows = $this->mentorBookingQuery($search, $startDate, $endDate)->get()
            ->map(fn (MentorBooking $booking) => $this->normalizeMentorBookingRow($booking));

        $demoRows = $this->demoBookingQuery($search, $startDate, $endDate)->get()
            ->map(fn (DemoBooking $booking) => $this->normalizeDemoBookingRow($booking));

        $rows = $mentorRows->concat($demoRows)->sortByDesc('sort_at')->values();
        $perPage = Helpers::getPagination();
        $page = max(1, (int) $request->get('page', 1));
        $total = $rows->count();

        return new LengthAwarePaginator(
            $rows->slice(($page - 1) * $perPage, $perPage)->values(),
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $queryParam]
        );
    }

    private function normalizeMentorBookingRow(MentorBooking $booking): array
    {
        $menteeName = trim(($booking->mentee?->f_name ?? '') . ' ' . ($booking->mentee?->l_name ?? ''));

        return [
            'kind' => 'mentor',
            'id' => $booking->id,
            'ref' => 'MB-' . $booking->id,
            'sort_at' => $booking->created_at?->timestamp ?? 0,
            'created_at' => $booking->created_at,
            'customer_name' => $menteeName !== '' ? $menteeName : '—',
            'customer_phone' => $booking->mentee?->phone,
            'customer_email' => $booking->mentee?->email,
            'mentor_or_category' => $booking->mentor?->display_name ?? '—',
            'service_or_stage' => $booking->service?->title ?? '—',
            'session_date' => $booking->preferred_date?->format('d M Y'),
            'amount' => $booking->amount + $booking->tax_amount,
            'status' => $booking->status,
            'payment_status' => $booking->payment_status,
            'show_url' => route('admin.mentor.bookings.show', $booking->id),
            'customer_url' => $booking->mentee_user_id
                ? route('admin.customer.view', $booking->mentee_user_id)
                : null,
        ];
    }

    private function normalizeDemoBookingRow(DemoBooking $booking): array
    {
        return [
            'kind' => 'demo',
            'id' => $booking->id,
            'ref' => $booking->booking_ref,
            'sort_at' => $booking->created_at?->timestamp ?? 0,
            'created_at' => $booking->created_at,
            'customer_name' => $booking->name,
            'customer_phone' => $booking->phone,
            'customer_email' => $booking->email,
            'mentor_or_category' => $booking->category_label ?: ($booking->category ?: 'Demo'),
            'service_or_stage' => $booking->stage ?: '—',
            'session_date' => null,
            'amount' => null,
            'status' => $booking->status,
            'payment_status' => null,
            'show_url' => route('admin.demo-bookings.show', $booking->id),
            'customer_url' => $booking->user_id ? route('admin.customer.view', $booking->user_id) : null,
        ];
    }

    /**
     * @param $id
     * @return Application|Factory|View|RedirectResponse
     */
    public function orderDetails($id): View|Factory|RedirectResponse|Application
    {
        $order = $this->order->with('details')->where(['id' => $id])->first();
        $deliverymanList = $this->delivery_man->where(['is_active'=>1])
            ->where(function($query) use ($order) {
                $query->where('branch_id', $order->branch_id)
                    ->orWhere('branch_id', 0);
            })
            ->get();

        if (isset($order)) {
            return view('admin-views.order.order-view', compact('order', 'deliverymanList'));
        } else {
            Toastr::info(translate('No more orders!'));
            return back();
        }
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function placeOrder(Request $request): RedirectResponse
    {
        if ($request->session()->has('cart')) {
            if (count($request->session()->get('cart')) < 1) {
                Toastr::error(translate('cart_empty_warning'));
                return back();
            }
        } else {
            Toastr::error(translate('cart_empty_warning'));
            return back();
        }

        $orderType = session()->has('order_type') ? session()->get('order_type') : 'take_away';

        $deliveryCharge = 0;
        if ($orderType == 'home_delivery'){
            if (!session()->has('customer_id')){
                Toastr::error(translate('please select a customer'));
                return back();
            }

            if (!session()->has('address')){
                Toastr::error(translate('please select a delivery address'));
                return back();
            }

            $addressData = session()->get('address');
            $distance = $addressData['distance'] ?? 0;
            $deliveryType = Helpers::get_business_settings('delivery_management');
            if ($deliveryType['status'] == 1){
                $deliveryCharge = Helpers::get_delivery_charge($distance);
            }else{
                $deliveryCharge = Helpers::get_business_settings('delivery_charge');
            }

            $address = [
                'address_type' => 'Home',
                'contact_person_name' => $addressData['contact_person_name'],
                'contact_person_number' => $addressData['contact_person_number'],
                'address' => $addressData['address'],
                'floor' => $addressData['floor'],
                'road' => $addressData['road'],
                'house' => $addressData['house'],
                'longitude' => (string)$addressData['longitude'],
                'latitude' => (string)$addressData['latitude'],
                'user_id' => session()->get('customer_id'),
                'is_guest' => 0,
            ];
            $customerAddress = CustomerAddress::create($address);
        }

        $cart = $request->session()->get('cart');
        $totalTaxAmount = 0;
        $productPrice = 0;
        $orderDetails = [];

        $orderId = 100000 + $this->order->all()->count() + 1;
        if ($this->order->find($orderId)) {
            $orderId = $this->order->orderBy('id', 'DESC')->first()->id + 1;
        }

        $order = $this->order;
        $order->id = $orderId;

        $order->user_id = session()->has('customer_id') ? session('customer_id') : null;
        $order->coupon_discount_title = $request->coupon_discount_title == 0 ? null : 'coupon_discount_title';
        $order->payment_status = $orderType == 'take_away' ? 'paid' : 'unpaid';
        $order->order_status = $orderType == 'take_away' ? 'delivered' : 'confirmed' ;
        $order->order_type = $orderType == 'take_away' ? 'pos' : 'delivery';
        $order->coupon_code = $request->coupon_code ?? null;
        $order->payment_method = $request->type;
        $order->transaction_reference = $request->transaction_reference ?? null;
        $order->delivery_charge = $deliveryCharge;
        $order->delivery_address_id = $orderType == 'home_delivery' ? $customerAddress->id : null;
        $order->delivery_date = Carbon::now()->format('Y-m-d');
        $order->order_note = null;
        $order->checked = 1;
        $order->created_at = now();
        $order->updated_at = now();

        foreach ($cart as $c) {
            if (is_array($c)) {
                $product = $this->product->find($c['id']);
                if(!empty($product['variations'])){
                    $type = $c['variant'];
                    foreach (json_decode($product['variations'], true) as $var) {
                        if ($type == $var['type'] && $var['stock'] < $c['quantity']) {
                            Toastr::error($var['type'] . ' ' . translate('is out of stock'));
                            return back();
                        }
                    }
                }else{
                    if(($product->total_stock - $c['quantity']) < 0) {
                        Toastr::error($product->name . ' ' . translate('is out of stock'));
                        return back();
                    }
                }
            }
        }

        foreach ($cart as $c) {
            if (is_array($c)) {

                $discountOnProduct = 0;
                $productSubtotal = ($c['price']) * $c['quantity'];
                $discountOnProduct += ($c['discount'] * $c['quantity']);

                $product = $this->product->find($c['id']);
                if ($product) {
                    $price = $c['price'];
                    $taxOnProduct = Helpers::tax_calculate($product, $price);

                    $categoryId = null;
                    foreach (json_decode($product['category_ids'], true) as $cat) {
                        if ($cat['position'] == 1){
                            $categoryId = ($cat['id']);
                        }
                    }

                    $categoryDiscount = Helpers::category_discount_calculate($categoryId, $price);
                    $productDiscount = Helpers::discount_calculate($product, $price);

                    if ($categoryDiscount >= $price){
                        $discount = $productDiscount;
                        $discount_type = 'discount_on_product';
                    }else{
                        $discount = max($categoryDiscount, $productDiscount);
                        $discount_type = $productDiscount > $categoryDiscount ? 'discount_on_product' : 'discount_on_category';
                    }

                    $product = Helpers::product_data_formatting($product);

                    $orderDetailsData = [
                        'product_id' => $c['id'],
                        'product_details' => $product,
                        'quantity' => $c['quantity'],
                        'price' => $price,
                        'tax_amount' => $taxOnProduct,
                        'discount_on_product' => $discount,
                        'discount_type' => $discount_type,
                        'variant' => json_encode($c['variant']),
                        'variation' => json_encode($c['variations']),
                        'vat_status' => Helpers::get_business_settings('product_vat_tax_status') === 'included' ? 'included' : 'excluded',
                        'created_at' => now(),
                        'updated_at' => now()
                    ];
                    $totalTaxAmount += $orderDetailsData['tax_amount'] * $c['quantity'];
                    $productPrice += $productSubtotal - $discountOnProduct;
                    $orderDetails[] = $orderDetailsData;
                }
                $var_store = [];
                if(!empty($product['variations'])){
                    $type = $c['variant'];
                    foreach ($product['variations'] as $var) {
                        if ($type == $var->type) {
                            $var->stock -= $c['quantity'];
                        }
                        $var_store[] = $var;
                    }
                }

                $this->product->where(['id' => $product['id']])->update([
                    'variations' => json_encode($var_store),
                    'total_stock' => $product['total_stock'] - $c['quantity'],
                    'popularity_count'=>$product['popularity_count']+1
                ]);
            }
        }

        $totalPrice = $productPrice;
        if (isset($cart['extra_discount'])) {
            $extraDiscount = $cart['extra_discount_type'] == 'percent' && $cart['extra_discount'] > 0 ? (($totalPrice * $cart['extra_discount']) / 100) : $cart['extra_discount'];
            $totalPrice -= $extraDiscount;
        }
        $tax = $cart['tax'] ?? 0;
        $totalTaxAmount = ($tax > 0) ? (($totalPrice * $tax) / 100) : $totalTaxAmount;
        try {
            $order->extra_discount = $extraDiscount ?? 0;
            $order->total_tax_amount = $totalTaxAmount;
            $order->order_amount = $totalPrice + $totalTaxAmount + $order->delivery_charge;
            $order->coupon_discount_amount = 0.00;
            $order->branch_id = session()->has('branch_id') ? session('branch_id') : 1;
            $order->save();

            foreach ($orderDetails as $key => $item) {
                $orderDetails[$key]['order_id'] = $order->id;
            }

            $this->order_detail->insert($orderDetails);

            if (session()->has('customer_id')){
                $emailServices = Helpers::get_business_settings('mail_config');
                $customer = $this->user->find($order->user_id);
                if (isset($emailServices['status']) && isset($customer->email) && $emailServices['status'] == 1) {
                    try {
                        Mail::to($customer->email)->send(new OrderPlaced($order->id));
                    }catch (\Exception $e) {
                        //
                    }
                }

                if ($orderType == 'home_delivery' && isset($customer)){
                    $customerFcmToken = $customer->cm_firebase_token;
                    $customerLanguageCode = $customer->language_code ?? 'en';
                    $message = Helpers::order_status_update_message('confirmed');

                    if ($customerLanguageCode != 'en'){
                        $message = $this->translate_message($customerLanguageCode, 'confirmed');
                    }

                    $order = $this->order->find($orderId);
                    $value = $this->dynamic_key_replaced_message(message: $message, type: 'order', order: $order);

                    try {
                        if ($value && $customerFcmToken != null) {
                            $data = [
                                'title' => 'Order',
                                'description' => $value,
                                'order_id' => $orderId,
                                'image' => '',
                                'type' => 'order'
                            ];
                            Helpers::send_push_notif_to_device($customerFcmToken, $data);
                        }
                    } catch (\Exception $e) {
                        //
                    }
                }

            }

            session()->forget('cart');
            session()->forget('customer_id');
            session()->forget('branch_id');
            session()->forget('address');
            session()->forget('order_type');
            session(['last_order' => $order->id]);

            Toastr::success(translate('order_placed_successfully'));
            return back();
        } catch (\Exception $e) {
            //
        }
        Toastr::warning(translate('failed_to_place_order'));
        return back();
    }

    /**
     * @param $id
     * @return JsonResponse
     */
    public function generateInvoice($id): \Illuminate\Http\JsonResponse
    {
        $order = $this->order->where('id', $id)->first();
        return response()->json([
            'success' => 1,
            'view' => view('admin-views.pos.order.invoice', compact('order'))->render(),
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function storeKeys(Request $request): \Illuminate\Http\JsonResponse
    {
        session()->put($request['key'], $request['value']);
        return response()->json('', 200);
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function newCustomerStore(Request $request): \Illuminate\Http\RedirectResponse
    {
        $validatedData = $request->validate([
            'f_name' => 'required',
            'l_name' => 'required',
            'email' => 'required|email|unique:users',
            'phone' => 'required|unique:users'
        ], [
            'f_name.required' => translate('first name is required'),
            'l_name.required' => translate('last name is required'),
            'email.required' => translate('email name is required'),
            'phone.required' => translate('phone name is required'),
            'email.unique' => translate('email must be unique'),
            'phone.unique' => translate('phone must be unique'),
        ]);

        $customer = $this->user;
        $customer->f_name = $request->f_name;
        $customer->l_name = $request->l_name;
        $customer->email = $request->email;
        $customer->phone = $request->phone;
        $customer->password = Hash::make('12345678');
        $customer->save();
        Toastr::success(translate('Customer added successfully!'));
        return back();
    }

    /**
     * @param Request $request
     * @return string|StreamedResponse
     * @throws IOException
     * @throws InvalidArgumentException
     * @throws UnsupportedTypeException
     * @throws WriterNotOpenedException
     */
    public function exportOrders(Request $request): StreamedResponse|string
    {
        $search = trim((string) $request->get('search', ''));
        $type = strtolower((string) $request->get('type', 'all'));
        if (!in_array($type, ['all', 'mentor', 'demo'], true)) {
            $type = 'all';
        }

        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $storage = [];

        if ($type === 'all' || $type === 'mentor') {
            foreach ($this->mentorBookingQuery($search, $startDate, $endDate)->get() as $booking) {
                $row = $this->normalizeMentorBookingRow($booking);
                $storage[] = [
                    'Type' => 'Mentor session',
                    'Reference' => $row['ref'],
                    'Created' => $row['created_at']?->format('d M Y H:i'),
                    'Customer' => $row['customer_name'],
                    'Phone' => $row['customer_phone'] ?: '',
                    'Email' => $row['customer_email'] ?: '',
                    'Mentor / Category' => $row['mentor_or_category'],
                    'Service / Stage' => $row['service_or_stage'],
                    'Session Date' => $row['session_date'] ?: '',
                    'Amount' => $row['amount'],
                    'Status' => $row['status'],
                    'Payment Status' => $row['payment_status'],
                ];
            }
        }

        if ($type === 'all' || $type === 'demo') {
            foreach ($this->demoBookingQuery($search, $startDate, $endDate)->get() as $booking) {
                $row = $this->normalizeDemoBookingRow($booking);
                $storage[] = [
                    'Type' => 'Demo booking',
                    'Reference' => $row['ref'],
                    'Created' => $row['created_at']?->format('d M Y H:i'),
                    'Customer' => $row['customer_name'],
                    'Phone' => $row['customer_phone'] ?: '',
                    'Email' => $row['customer_email'] ?: '',
                    'Mentor / Category' => $row['mentor_or_category'],
                    'Service / Stage' => $row['service_or_stage'],
                    'Session Date' => '',
                    'Amount' => '',
                    'Status' => $row['status'],
                    'Payment Status' => '',
                ];
            }
        }

        usort($storage, fn ($a, $b) => strcmp($b['Created'], $a['Created']));

        return (new FastExcel($storage))->download('bookings.xlsx');
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function addDeliveryInfo(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'contact_person_name' => 'required',
            'contact_person_number' => 'required',
            'address' => 'required',
            'latitude' => 'required',
            'longitude' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 200);
        }

        $branchId = session()->get('branch_id') ?? 1;
        $branch = $this->branch->find($branchId);
        $originLat = $branch['latitude'];
        $originLng = $branch['longitude'];
        $destinationLat = $request['latitude'];
        $destinationLng = $request['longitude'];

        $mapApiKey = Helpers::get_business_settings('map_api_server_key');
        $response = Http::get('https://maps.googleapis.com/maps/api/distancematrix/json?origins=' . $originLat . ',' . $originLng . '&destinations=' . $destinationLat . ',' . $destinationLng . '&key=' . $mapApiKey);

        $data = json_decode($response, true);
        $distanceValue = $data['rows'][0]['elements'][0]['distance']['value'];
        $distance = $distanceValue/1000;

        $address = [
            'contact_person_name' => $request->contact_person_name,
            'contact_person_number' => $request->contact_person_number,
            'address_type' => 'Home',
            'address' => $request->address,
            'floor' => $request->floor,
            'road' => $request->road,
            'house' => $request->house,
            'distance' => $distance,
            'longitude' => (string)$request->longitude,
            'latitude' => (string)$request->latitude,
        ];

        $request->session()->put('address', $address);

        return response()->json([
            'data' => $address,
            'view' => view('admin-views.pos._address', compact('address'))->render(),
        ]);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getDistance(Request $request): mixed
    {
        $request->validate([
            'origin_lat' => 'required',
            'origin_lng' => 'required',
            'destination_lat' => 'required',
            'destination_lng' => 'required',
        ]);

        $mapApiKey = Helpers::get_business_settings('map_api_server_key');
        $response = Http::get('https://maps.googleapis.com/maps/api/distancematrix/json?origins=' . $request['origin_lat'] . ',' . $request['origin_lng'] . '&destinations=' . $request['destination_lat'] . ',' . $request['destination_lng'] . '&key=' . $mapApiKey);

        return $response->json();
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function orderTypeStore(Request $request): JsonResponse
    {
        session()->put('order_type', $request['order_type']);
        return response()->json($request['order_type'], 200);
    }


}
