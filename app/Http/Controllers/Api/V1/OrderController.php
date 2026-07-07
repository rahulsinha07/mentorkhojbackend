<?php

namespace App\Http\Controllers\Api\V1;

use App\CentralLogics\CustomerLogic;
use App\CentralLogics\Helpers;
use App\CentralLogics\OrderLogic;
use App\Http\Controllers\Controller;
use App\Mail\Customer\OrderPlaced;
use App\Model\Coupon;
use App\Model\CustomerAddress;
use App\Model\DMReview;
use App\Model\Order;
use App\Model\OrderDetail;
use App\Model\Product;
use App\Model\Review;
use App\Models\GuestUser;
use App\Models\OfflinePayment;
use App\Models\OrderImage;
use App\Models\OrderPartialPayment;
use App\Traits\HelperTrait;
use App\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use function App\CentralLogics\translate;

class OrderController extends Controller
{
    use HelperTrait;
    public function __construct(
        private Coupon $coupon,
        private DMReview $deliverymanReview,
        private Order $order,
        private OrderDetail $orderDetail,
        private Product $product,
        private Review $review,
        private User $user
    ){}

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function trackOrder(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required',
            'phone' => 'nullable'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $phone = $request->input('phone');
        $userId = (bool)auth('api')->user() ? auth('api')->user()->id : $request->header('guest-id');
        $userType = (bool)auth('api')->user() ? 0 : 1;

        $order = $this->order->find($request['order_id']);

        if (!isset($order)){
            return response()->json([
                'errors' => [['code' => 'order', 'message' => 'Order not found!']]], 404);
        }

        if (!is_null($phone)){
            if ($order['is_guest'] == 0){
                $trackOrder = $this->order
                    ->with(['customer', 'delivery_address'])
                    ->where(['id' => $request['order_id']])
                    ->whereHas('customer', function ($customerSubQuery) use ($phone) {
                        $customerSubQuery->where('phone', $phone);
                    })
                    ->first();
            }else{
                $trackOrder = $this->order
                    ->with(['delivery_address'])
                    ->where(['id' => $request['order_id']])
                    ->whereHas('delivery_address', function ($addressSubQuery) use ($phone) {
                        $addressSubQuery->where('contact_person_number', $phone);
                    })
                    ->first();
            }
        }else{
            $trackOrder = $this->order
                ->where(['id' => $request['order_id'], 'user_id' => $userId, 'is_guest' => $userType])
                ->first();
        }

        if (!isset($trackOrder)){
            return response()->json([
                'errors' => [['code' => 'order', 'message' => 'Order not found!']]], 404);
        }

        return response()->json(OrderLogic::track_order($request['order_id']), 200);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function placeOrder(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'order_amount' => 'required',
            'payment_method'=>'required',
            'delivery_address_id' => 'required',
            'order_type' => 'required|in:self_pickup,delivery',
            'branch_id' => 'required',
            'distance' => 'required',
            'is_partial' => 'required|in:0,1',
            'order_images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        if (count($request['cart']) <1) {
            return response()->json(['errors' => [['code' => 'empty-cart', 'message' => translate('cart is empty')]]], 403);
        }

        $userId = (bool)auth('api')->user() ? auth('api')->user()->id : $request->header('guest-id');
        $userType = (bool)auth('api')->user() ? 0 : 1;

        if(auth('api')->user()){
            $customer = $this->user->find(auth('api')->user()->id);
        }

        $minimumAmount = Helpers::get_business_settings('minimum_order_value');
        if ($minimumAmount > $request['order_amount']){
            $errors = [];
            $errors[] = ['code' => 'auth-001', 'message' => 'Order amount must be equal or more than '. $minimumAmount];
            return response()->json(['errors' => $errors], 401);
        }

        $maximumAmount = Helpers::get_business_settings('maximum_amount_for_cod_order');
        if ($request->payment_method == 'cash_on_delivery' && Helpers::get_business_settings('maximum_amount_for_cod_order_status') == 1 && ($maximumAmount < $request['order_amount'])){
            $errors = [];
            $errors[] = ['code' => 'auth-001', 'message' => 'For Cash on Delivery, order amount must be equal or less than '. $maximumAmount];
            return response()->json(['errors' => $errors], 401);
        }

        $userId = (bool)auth('api')->user() ? auth('api')->user()->id : $request->header('guest-id');
        $userType = (bool)auth('api')->user() ? 0 : 1;


        if($request->payment_method == 'wallet_payment' && Helpers::get_business_settings('wallet_status') != 1)
        {
            return response()->json(['errors' => [['code' => 'payment_method', 'message' => translate('customer_wallet_status_is_disable')]]], 403);
        }

        if($request->payment_method == 'wallet_payment' && $customer->wallet_balance < $request['order_amount'])
        {
            return response()->json([
                'errors' => [['code' => 'payment_method', 'message' => translate('you_do_not_have_sufficient_balance_in_wallet')]]], 403);
        }

        if ($request['is_partial'] == 1) {
            if (Helpers::get_business_settings('wallet_status') != 1){
                return response()->json(['errors' => [['code' => 'payment_method', 'message' => translate('customer_wallet_status_is_disable')]]], 403);
            }
            if (isset($customer) && $customer->wallet_balance > $request['order_amount']){
                return response()->json(['errors' => [['code' => 'payment_method', 'message' => translate('since your wallet balance is more than order amount, you can not place partial order')]]], 403);
            }
            if (isset($customer) && $customer->wallet_balance < 1){
                return response()->json(['errors' => [['code' => 'payment_method', 'message' => translate('since your wallet balance is less than 1, you can not place partial order')]]], 403);
            }
        }

        foreach ($request['cart'] as $c) {
            $product = $this->product->find($c['product_id']);
            if (count(json_decode($product['variations'], true)) > 0) {
                $type = $c['variation'][0]['type'];
                foreach (json_decode($product['variations'], true) as $var) {
                    if ($type == $var['type'] && $var['stock'] < $c['quantity']) {
                        $validator->getMessageBag()->add('stock', 'One or more product stock is insufficient!');
                    }
                }
            } else {
                if ($product['total_stock'] < $c['quantity']) {
                    $validator->getMessageBag()->add('stock', 'One or more product stock is insufficient!');
                }
            }
        }

        if ($validator->getMessageBag()->count() > 0) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }


        $freeDeliveryAmount = 0;
        if ($request['order_type'] == 'self_pickup'){
            $deliveryCharge = 0;
        } elseif (Helpers::get_business_settings('free_delivery_over_amount_status') == 1 && (Helpers::get_business_settings('free_delivery_over_amount') <= $request['order_amount'])){
            $deliveryCharge = 0;
            $freeDeliveryAmount = Helpers::get_delivery_charge($request['distance']);
        } else{
            $deliveryCharge = Helpers::get_delivery_charge($request['distance']);
        }

        $coupon = $this->coupon->active()->where(['code' => $request['coupon_code']])->first();

        if (isset($coupon)) {
            if ($coupon['coupon_type'] == 'free_delivery') {
                $freeDeliveryAmount = Helpers::get_delivery_charge($request['distance']);
                $couponDiscount = 0;
                $deliveryCharge = 0;
            } else {
                $couponDiscount = $request['coupon_discount_amount'];
            }
        }else{
            $couponDiscount = $request['coupon_discount_amount'];
        }

        if ($request['is_partial'] == 1) {
            $paymentStatus = ($request->payment_method == 'cash_on_delivery' || $request->payment_method == 'offline_payment') ? 'partially_paid' : 'paid';
        } else {
            $paymentStatus = ($request->payment_method == 'cash_on_delivery' || $request->payment_method == 'offline_payment') ? 'unpaid' : 'paid';
        }

        $orderStatus = ($request->payment_method == 'cash_on_delivery' || $request->payment_method == 'offline_payment') ? 'pending' : 'confirmed';

        try {
            DB::beginTransaction();
            $orderId = 100000 + Order::all()->count() + 1;
            $or = [
                'id' => $orderId,
                'user_id' => $userId,
                'is_guest' => $userType,
                'order_amount' => $request['order_amount'],
                'coupon_code' =>  $request['coupon_code'],
                'coupon_discount_amount' => $couponDiscount,
                'coupon_discount_title' => $request->coupon_discount_title == 0 ? null : 'coupon_discount_title',
                'payment_status' => $paymentStatus,
                'order_status' => $orderStatus,
                'payment_method' => $request->payment_method,
                'transaction_reference' => $request->transaction_reference ?? null,
                'order_note' => $request['order_note'],
                'order_type' => $request['order_type'],
                'branch_id' => $request['branch_id'],
                'delivery_address_id' => $request->delivery_address_id,
                'time_slot_id' => $request->time_slot_id,
                'delivery_date' => $request->delivery_date,
                'delivery_address' => json_encode(CustomerAddress::find($request->delivery_address_id) ?? null),
                'date' => date('Y-m-d'),
                'delivery_charge' => $deliveryCharge,
                'payment_by' => $request['payment_method'] == 'offline_payment' ? $request['payment_by'] : null,
                'payment_note' => $request['payment_method'] == 'offline_payment' ? $request['payment_note'] : null,
                'free_delivery_amount' => $freeDeliveryAmount,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $orderTimeSlotId = $or['time_slot_id'];
            $orderDeliveryDate = $or['delivery_date'];

            $totalTaxAmount = 0;

            foreach ($request['cart'] as $c) {
                $product = $this->product->find($c['product_id']);

                if ($product['maximum_order_quantity'] < $c['quantity']){
                    return response()->json(['errors' => $product['name']. ' '. translate('quantity_must_be_equal_or_less_than '. $product['maximum_order_quantity'])], 401);
                }

                if (count(json_decode($product['variations'], true)) > 0) {
                    $price = Helpers::variation_price($product, json_encode($c['variation']));
                } else {
                    $price = $product['price'];
                }

                $tax_on_product = Helpers::tax_calculate($product, $price);

                $category_id = null;
                foreach (json_decode($product['category_ids'], true) as $cat) {
                    if ($cat['position'] == 1){
                        $category_id = ($cat['id']);
                    }
                }

                $category_discount = Helpers::category_discount_calculate($category_id, $price);
                $product_discount = Helpers::discount_calculate($product, $price);
                if ($category_discount >= $price){
                    $discount = $product_discount;
                    $discount_type = 'discount_on_product';
                }else{
                    $discount = max($category_discount, $product_discount);
                    $discount_type = $product_discount > $category_discount ? 'discount_on_product' : 'discount_on_category';
                }

                $or_d = [
                    'order_id' => $orderId,
                    'product_id' => $c['product_id'],
                    'time_slot_id' => $orderTimeSlotId,
                    'delivery_date' => $orderDeliveryDate,
                    'product_details' => $product,
                    'quantity' => $c['quantity'],
                    'price' => $price,
                    'unit' => $product['unit'],
                    'tax_amount' => $tax_on_product,
                    'discount_on_product' => $discount,
                    'discount_type' => $discount_type,
                    'variant' => json_encode($c['variant']),
                    'variation' => json_encode($c['variation']),
                    'is_stock_decreased' => 1,
                    'vat_status' => Helpers::get_business_settings('product_vat_tax_status') === 'included' ? 'included' : 'excluded',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $totalTaxAmount += $or_d['tax_amount'] * $c['quantity'];

                $type = $c['variation'][0]['type'];
                $variationStore = [];
                foreach (json_decode($product['variations'], true) as $var) {
                    if ($type == $var['type']) {
                        $var['stock'] -= $c['quantity'];
                    }
                    $variationStore[] = $var;
                }

                $this->product->where(['id' => $product['id']])->update([
                    'variations' => json_encode($variationStore),
                    'total_stock' => $product['total_stock'] - $c['quantity'],
                    'popularity_count'=>$product['popularity_count']+1
                ]);

                DB::table('order_details')->insert($or_d);
            }
            $or['total_tax_amount'] = $totalTaxAmount;
            DB::table('orders')->insertGetId($or);

            if($request->payment_method == 'wallet_payment'){
                $amount = $or['order_amount'];
                CustomerLogic::create_wallet_transaction($or['user_id'], $amount, 'order_place', $or['id']);
            }

            if ($request->payment_method == 'offline_payment') {
                $offlinePayment = new OfflinePayment();
                $offlinePayment->order_id = $or['id'];
                $offlinePayment->payment_info = json_encode($request['payment_info']);
                $offlinePayment->save();
            }

            if ($request['is_partial'] == 1){
                $totalOrderAmount = $or['order_amount'];
                $walletAmount = $customer->wallet_balance;
                $dueAmount = $totalOrderAmount - $walletAmount;

                $walletTransaction = CustomerLogic::create_wallet_transaction($or['user_id'], $walletAmount, 'order_place', $or['id']);

                $partial = new OrderPartialPayment();
                $partial->order_id = $or['id'];
                $partial->paid_with = 'wallet_payment';
                $partial->paid_amount = $walletAmount;
                $partial->due_amount = $dueAmount;
                $partial->save();

                if ($request['payment_method'] != 'cash_on_delivery'){
                    $partial = new OrderPartialPayment;
                    $partial->order_id = $or['id'];
                    $partial->paid_with = $request['payment_method'];
                    $partial->paid_amount = $dueAmount;
                    $partial->due_amount = 0;
                    $partial->save();
                }
            }

            if (Helpers::get_business_settings('order_image_status') == 1 && !empty($request->file('order_images'))){
                self::uploadOrderImage(orderImages: $request->order_images, orderId: $orderId);
            }

            DB::commit();

            if ((bool)auth('api')->user()){
                $customerFcmToken = auth('api')->user()->cm_firebase_token;
                $languageCode = auth('api')->user()->language_code ?? 'en';
            }else{
                $guest = GuestUser::find($request->header('guest-id'));
                $customerFcmToken = $guest ? $guest->fcm_token : '';
                $languageCode = $guest ? $guest->language_code : 'en';
            }

            $orderStatusMessage = ($request->payment_method == 'cash_on_delivery' || $request->payment_method == 'offline_payment') ? 'pending':'confirmed';
            $message = Helpers::order_status_update_message($orderStatusMessage);

            if ($languageCode != 'en'){
                $message = $this->translate_message($languageCode, $orderStatusMessage);
            }

            $order = $this->order->find($orderId);
            $value = $this->dynamic_key_replaced_message(message: $message, type: 'order', order: $order);

            try {
                if ($value) {
                    $data = [
                        'title' => 'Order',
                        'description' => $value,
                        'order_id' => $orderId,
                        'image' => '',
                        'type' => 'order'
                    ];
                    Helpers::send_push_notif_to_device($customerFcmToken, $data);
                }

                $emailServices = Helpers::get_business_settings('mail_config');
                if (isset($emailServices['status']) && $emailServices['status'] == 1 && isset($customer->email)) {
                    Mail::to($customer->email)->send(new OrderPlaced($orderId));
                }

            } catch (\Exception $e) {
            }

            return response()->json([
                'message' => 'Order placed successfully!',
                'order_id' => $orderId,
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([$e], 403);
        }
    }

    /**
     * @param $orderImages
     * @param $orderId
     * @return true
     */
    private function uploadOrderImage($orderImages, $orderId): bool
    {
        foreach ($orderImages as $image) {
            $image = Helpers::upload('order/', 'png', $image);
            $orderImage = new OrderImage();
            $orderImage->order_id = $orderId;
            $orderImage->image = $image;
            $orderImage->save();
        }
        return true;
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getOrderList(Request $request): \Illuminate\Http\JsonResponse
    {
        $userId = (bool)auth('api')->user() ? auth('api')->user()->id : $request->header('guest-id');
        $userType = (bool)auth('api')->user() ? 0 : 1;

        $orders = $this->order->with(['customer', 'delivery_man.rating', 'details:id,order_id,quantity'])
            ->where(['user_id' => $userId, 'is_guest' => $userType])
            ->get();

        $orders->each(function ($order) {
            $order->total_quantity = $order->details->sum('quantity');
        });

        $orders->map(function ($data) {
            $data['deliveryman_review_count'] = $this->deliverymanReview->where(['delivery_man_id' => $data['delivery_man_id'], 'order_id' => $data['id']])->count();
            return $data;
        });

        return response()->json($orders->map(function ($data) {
            $data->details_count = (integer)$data->details_count;
            return $data;
        }), 200);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getOrderDetails(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required',
            'phone' => 'nullable'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $phone = $request->input('phone');
        $userId = (bool)auth('api')->user() ? auth('api')->user()->id : $request->header('guest-id');
        $userType = (bool)auth('api')->user() ? 0 : 1;

        $order = $this->order->find($request['order_id']);
        if (!isset($order)){
            return response()->json([
                'errors' => [['code' => 'order', 'message' => 'Order not found!']]], 404);
        }

        if (!is_null($phone)){
            if ($order['is_guest'] == 0){
                $details = $this->orderDetail
                    ->with(['order', 'order.delivery_address' ,'order.customer', 'order.partial_payment', 'order.offline_payment', 'order.order_image'])
                    ->where(['order_id' => $request['order_id']])
                    ->whereHas('order.customer', function ($customerSubQuery) use ($phone) {
                        $customerSubQuery->where('phone', $phone);
                    })
                    ->get();
            }else{
                $details = $this->orderDetail
                    ->with(['order', 'order.delivery_address', 'order.partial_payment', 'order.offline_payment', 'order.order_image'])
                    ->where(['order_id' => $request['order_id']])
                    ->whereHas('order.delivery_address', function ($addressSubQuery) use ($phone) {
                        $addressSubQuery->where('contact_person_number', $phone);
                    })
                    ->get();
            }
        }else{
            $details = $this->orderDetail
                ->with(['order', 'order.partial_payment', 'order.offline_payment'])
                ->where(['order_id' => $request['order_id']])
                ->whereHas('order', function ($q) use ($userId, $userType){
                    $q->where(['user_id' => $userId, 'is_guest' => $userType]);
                })
                ->orderBy('id', 'desc')
                ->get();
        }


        if ($details->count() > 0) {
            foreach ($details as $detail) {

                $keepVariation = $detail['variation'];

                $variation = json_decode($detail['variation'], true);

                $detail['add_on_ids'] = json_decode($detail['add_on_ids']);
                $detail['add_on_qtys'] = json_decode($detail['add_on_qtys']);
                if (gettype(json_decode($keepVariation)) == 'array'){
                    $new_variation = json_decode($keepVariation);
                }else{
                    $new_variation = [];
                    $new_variation[] = json_decode($detail['variation']);

                }

                $detail['variation'] = $new_variation;

                $detail['formatted_variation'] = $new_variation[0] ?? null;
                if (isset($new_variation[0]) && $new_variation[0]->type == null){
                    $detail['formatted_variation'] = null;
                }

                $detail['review_count'] = $this->review->where(['order_id' => $detail['order_id'], 'product_id' => $detail['product_id']])->count();
                $detail['product_details'] = Helpers::product_data_formatting(json_decode($detail['product_details'], true));

            }
            return response()->json($details, 200);
        } else {
            return response()->json([
                'errors' => [
                    ['code' => 'order', 'message' => 'Order not found!']
                ]
            ], 404);
        }
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function cancelOrder(Request $request): \Illuminate\Http\JsonResponse
    {
        $order = $this->order::find($request['order_id']);

        if (!isset($order)){
            return response()->json(['errors' => [['code' => 'order', 'message' => 'Order not found!']]], 404);
        }

        if ($order->order_status != 'pending'){
            return response()->json(['errors' => [['code' => 'order', 'message' => 'Order can only cancel when order status is pending!']]], 403);
        }

        $userId = (bool)auth('api')->user() ? auth('api')->user()->id : $request->header('guest-id');
        $userType = (bool)auth('api')->user() ? 0 : 1;

        if ($this->order->where(['user_id' => $userId, 'is_guest' => $userType, 'id' => $request['order_id']])->first()) {

            $order = $this->order->with(['details'])->where(['user_id' => $userId, 'is_guest' => $userType, 'id' => $request['order_id']])->first();

            foreach ($order->details as $detail) {
                if ($detail['is_stock_decreased'] == 1) {
                    $product = $this->product->find($detail['product_id']);
                    if (isset($product)){
                        $type = json_decode($detail['variation'])[0]->type;
                        $variationStore = [];
                        foreach (json_decode($product['variations'], true) as $var) {
                            if ($type == $var['type']) {
                                $var['stock'] += $detail['quantity'];
                            }
                            $variationStore[] = $var;
                        }

                        $this->product->where(['id' => $product['id']])->update([
                            'variations' => json_encode($variationStore),
                            'total_stock' => $product['total_stock'] + $detail['quantity'],
                        ]);

                        $this->orderDetail->where(['id' => $detail['id']])->update([
                            'is_stock_decreased' => 0,
                        ]);
                    }
                }
            }
            $this->order->where(['user_id' => $userId, 'is_guest' => $userType, 'id' => $request['order_id']])->update([
                'order_status' => 'canceled',
            ]);
            return response()->json(['message' => 'Order canceled'], 200);
        }
        return response()->json([
            'errors' => [
                ['code' => 'order', 'message' => 'not found!'],
            ],
        ], 401);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function updatePaymentMethod(Request $request): \Illuminate\Http\JsonResponse
    {
        $userId = (bool)auth('api')->user() ? auth('api')->user()->id : $request->header('guest-id');
        $userType = (bool)auth('api')->user() ? 0 : 1;

        if ($this->order->where(['user_id' => $userId, 'is_guest' => $userType, 'id' => $request['order_id']])->first()) {
            $this->order->where(['user_id' => $userId, 'is_guest' => $userType, 'id' => $request['order_id']])->update([
                'payment_method' => $request['payment_method'],
            ]);
            return response()->json(['message' => 'Payment method is updated.'], 200);
        }
        return response()->json([
            'errors' => [
                ['code' => 'order', 'message' => 'not found!'],
            ],
        ], 401);
    }
}
