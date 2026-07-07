<?php

namespace App\Http\Controllers\Branch;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Mail\Customer\OrderPlaced;
use App\Model\Branch;
use App\Model\Category;
use App\Model\CustomerAddress;
use App\Model\DeliveryMan;
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
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Rap2hpoutre\FastExcel\FastExcel;
use Symfony\Component\HttpFoundation\StreamedResponse;
use function App\CentralLogics\translate;

class POSController extends Controller
{
    use HelperTrait;
    public function __construct(
        private Branch $branch,
        private Category $category,
        private Order $order,
        private OrderDetail $orderDetail,
        private Product $product,
        private User $user,
        private DeliveryMan $deliveryman
    ){}

    /**
     * @param Request $request
     * @return Application|Factory|View
     */
    public function index(Request $request): View|Factory|Application
    {
        $category = $request->query('category_id', 0);
        $categories = $this->category->where(['position' => 0])->active()->get();
        $keyword = $request->keyword;
        $key = explode(' ', $keyword);
        $users = $this->user->all();

        $products = $this->product->
            when($request->has('category_id') && $request['category_id'] != 0, function ($query) use ($request) {
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

        $branch = $this->branch->find(auth('branch')->id());
        return view('branch-views.pos.index', compact('categories', 'products', 'category', 'keyword', 'branch', 'users'));
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function quickView(Request $request): \Illuminate\Http\JsonResponse
    {
        $product = $this->product->findOrFail($request->product_id);
        $discount = self::discountCalculation($product, $product['price']);

        return response()->json([
            'success' => 1,
            'view' => view('branch-views.pos._quick-view-data', compact('product', 'discount'))->render(),
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
        $quantity = 0;
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
                }
            }
        } else {
            $price = $product->price;
            $discount = self::discountCalculation($product, $price);
            $price = $price - $discount;
        }

        return array('price' => Helpers::set_symbol(($price * $request->quantity)));
    }

    /**
     * @param $product
     * @param $price
     * @return float
     */
    public function discountCalculation($product, $price): float
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
    public function getCustomers(Request $request): JsonResponse
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
    public function update_discount(Request $request): RedirectResponse
    {
        $total = session()->get('total');

        if ($request->type == 'percent' && $request->discount < 0) {
            Toastr::error(translate('Extra_discount_can_not_be_less_than_0_percent'));
            return back();
        } elseif ($request->type == 'percent' && $request->discount > 100) {
            Toastr::error(translate('Extra_discount_can_not_be_more_than_100_percent'));
            return back();
        }elseif ($request->type == 'amount' && $request->discount > $total) {
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
    public function updateQuantity(Request $request): JsonResponse
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
        return view('branch-views.pos._cart');
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function emptyCart(Request $request): JsonResponse
    {
        session()->forget('cart');
        session()->forget('customer_id');
        session()->forget('address');
        session()->forget('order_type');
        return response()->json([], 200);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function removeFromCart(Request $request): JsonResponse
    {
        if ($request->session()->has('cart')) {
            $cart = $request->session()->get('cart', collect([]));
            $cart->forget($request->key);
            $request->session()->put('cart', $cart);
        }

        return response()->json([], 200);
    }

    //order

    /**
     * @param Request $request
     * @return Factory|View|Application
     */
    public function order_list(Request $request): View|Factory|Application
    {
        $queryParam = [];
        $search = $request['search'];
        $startDate = $request['start_date'];
        $endDate = $request['end_date'];

        $this->order->where(['checked' => 0])->update(['checked' => 1]);

        $query = $this->order->pos()->where(['branch_id' => auth('branch')->id()])->with(['customer', 'branch'])
                ->when((!is_null($startDate) && !is_null($endDate)), function ($query) use ($startDate, $endDate) {
                    return $query->whereDate('created_at', '>=', $startDate)
                    ->whereDate('created_at', '<=', $endDate);
                });

        $queryParam = ['start_date' => $startDate,'end_date' => $endDate ];

        if ($request->has('search')) {
            $key = explode(' ', $request['search']);
            $query = $query->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('id', 'like', "%{$value}%")
                        ->orWhere('order_status', 'like', "%{$value}%")
                        ->orWhere('payment_status', 'like', "{$value}%")
                        ->orWhere('transaction_reference', 'like', "%{$value}%");
                }
            });
            $queryParam = ['search' => $request['search']];
        }

        $orders = $query->latest()->paginate(Helpers::getPagination())->appends($queryParam);

        return view('branch-views.pos.order.list', compact('orders', 'search', 'startDate', 'endDate'));
    }

    /**
     * @param $id
     * @return Application|Factory|View|RedirectResponse
     */
    public function order_details($id): View|Factory|RedirectResponse|Application
    {
        $order = $this->order->with('details')->where(['id' => $id, 'branch_id' => auth('branch')->id()])->first();
        $deliverymanList = $this->deliveryman->where(['is_active'=>1])
            ->where(function($query) use ($order) {
                $query->where('branch_id', auth('branch')->id())
                    ->orWhere('branch_id', 0);
            })
            ->get();
        if (isset($order)) {
            return view('branch-views.order.order-view', compact('order', 'deliverymanList'));
        } else {
            Toastr::info(translate('No more orders!'));
            return back();
        }
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function place_order(Request $request): RedirectResponse
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
                        $discountType = 'discount_on_product';
                    }else{
                        $discount = max($categoryDiscount, $productDiscount);
                        $discountType = $productDiscount > $categoryDiscount ? 'discount_on_product' : 'discount_on_category';
                    }

                    $product = Helpers::product_data_formatting($product);

                    $or_d = [
                        'product_id' => $c['id'],
                        'product_details' => $product,
                        'quantity' => $c['quantity'],
                        'price' => $price,
                        'tax_amount' => Helpers::tax_calculate($product, $price),
                        'discount_on_product' => $discount,
                        'discount_type' => $discountType,
                        'variant' => json_encode($c['variant']),
                        'variation' => json_encode($c['variations']),
                        'vat_status' => Helpers::get_business_settings('product_vat_tax_status') === 'included' ? 'included' : 'excluded',
                        'created_at' => now(),
                        'updated_at' => now()
                    ];
                    $totalTaxAmount += $or_d['tax_amount'] * $c['quantity'];
                    $productPrice += $productSubtotal - $discountOnProduct;
                    $orderDetails[] = $or_d;
                }

                $variationStore = [];
                if(!empty($product['variations'])){
                    $type = $c['variant'];
                    foreach ($product['variations'] as $var) {
                        if ($type == $var->type) {
                            $var->stock -= $c['quantity'];
                        }
                        $variationStore[] = $var;
                    }
                }

                $this->product->where(['id' => $product['id']])->update([
                    'variations' => json_encode($variationStore),
                    'total_stock' => $product['total_stock'] - $c['quantity'],
                    'popularity_count'=>$product['popularity_count']+1
                ]);
            }
        }

        $totalPrice = $productPrice;
        if (isset($cart['extra_discount'])) {
            $extra_discount = $cart['extra_discount_type'] == 'percent' && $cart['extra_discount'] > 0 ? (($totalPrice * $cart['extra_discount']) / 100) : $cart['extra_discount'];
            $totalPrice -= $extra_discount;
        }
        $tax = $cart['tax'] ?? 0;
        $totalTaxAmount = ($tax > 0) ? (($totalPrice * $tax) / 100) : $totalTaxAmount;
        try {
            $order->extra_discount = $extra_discount ?? 0;
            $order->total_tax_amount = $totalTaxAmount;
            $order->order_amount = $totalPrice + $totalTaxAmount + $order->delivery_charge;
            $order->coupon_discount_amount = 0.00;
            $order->branch_id = auth('branch')->id();
            $order->save();

            foreach ($orderDetails as $key => $item) {
                $orderDetails[$key]['order_id'] = $order->id;
            }

            $this->orderDetail->insert($orderDetails);

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
            session()->forget('address');
            session()->forget('order_type');
            session(['last_order' => $order->id]);
            Toastr::success(translate('order_placed_successfully'));
            return back();
        } catch (\Exception $e) {
            info($e);
        }
        Toastr::warning(translate('failed_to_place_order'));
        return back();
    }

    /**
     * @param $id
     * @return JsonResponse
     */
    public function generateInvoice($id): JsonResponse
    {
        $order = $this->order->where('id', $id)->first();
        return response()->json([
            'success' => 1,
            'view' => view('branch-views.pos.order.invoice', compact('order'))->render(),
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function storeKeys(Request $request): JsonResponse
    {
        session()->put($request['key'], $request['value']);
        return response()->json('', 200);
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function newCustomerStore(Request $request): RedirectResponse
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
     * @return StreamedResponse|string
     * @throws IOException
     * @throws InvalidArgumentException
     * @throws UnsupportedTypeException
     * @throws WriterNotOpenedException
     */
    public function exportOrders(Request $request): StreamedResponse|string
    {
        $queryParam = [];
        $search = $request['search'];
        $startDate = $request['start_date'];
        $endDate = $request['end_date'];

        $query = $this->order->pos()->where(['branch_id' => auth('branch')->id()])->with(['customer', 'branch'])
            ->when((!is_null($startDate) && !is_null($endDate)), function ($query) use ($startDate, $endDate) {
                return $query->whereDate('created_at', '>=', $startDate)
                    ->whereDate('created_at', '<=', $endDate);
            });

        if ($request->has('search')) {
            $key = explode(' ', $request['search']);
            $query = $query->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('id', 'like', "%{$value}%")
                        ->orWhere('order_status', 'like', "%{$value}%")
                        ->orWhere('payment_status', 'like', "%{$value}%")
                        ->orWhere('transaction_reference', 'like', "%{$value}%");
                }
            });
            $queryParam = ['search' => $request['search']];
        }

        $orders = $query->with('details')->orderBy('id', 'DESC')->get();

        $storage = [];
        foreach($orders as $order){
            $vatStatus = $order->details[0] ? $order->details[0]->vat_status : '';
            if($vatStatus == 'included'){
                $orderAmount = $order['order_amount'] - $order['total_tax_amount'];
            }else{
                $orderAmount = $order['order_amount'];
            }

            $branch = $order->branch ? $order->branch->name : '';
            $customer = $order->customer ? $order->customer->f_name .' '. $order->customer->l_name : 'Walking Customer';
            $storage[] = [
                'order_id' => $order['id'],
                'customer' => $customer,
                'order_amount' => $orderAmount,
                'coupon_discount_amount' => $order['coupon_discount_amount'],
                'payment_status' => $order['payment_status'],
                'order_status' => $order['order_status'],
                'total_tax_amount'=> $order['total_tax_amount'],
                'payment_method'=> $order['payment_method'],
                'order_type'=> $order['order_type'],
                'branch'=> $branch,
                'delivery_date'=>$order['delivery_date'],
            ];
        }
        return (new FastExcel($storage))->download('pos-orders.xlsx');
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

        $branchId = auth('branch')->id();
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
