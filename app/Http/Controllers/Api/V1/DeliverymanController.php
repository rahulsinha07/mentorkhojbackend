<?php

namespace App\Http\Controllers\Api\V1;

use App\CentralLogics\CustomerLogic;
use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Model\BusinessSetting;
use App\Model\DeliveryHistory;
use App\Model\DeliveryMan;
use App\Model\Order;
use App\Models\OrderPartialPayment;
use App\Traits\HelperTrait;
use App\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DeliverymanController extends Controller
{
    use HelperTrait;
    public function __construct(
        private BusinessSetting $businessSetting,
        private DeliveryHistory $deliveryHistory,
        private DeliveryMan $deliveryman,
        private Order $order,
        private User $user
    ){}

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getProfile(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $deliveryman = $this->deliveryman->where(['auth_token' => $request['token']])->first();
        if (!isset($deliveryman)) {
            return response()->json([
                'errors' => [
                    ['code' => '401', 'message' => 'Invalid token!']
                ]
            ], 401);
        }
        return response()->json($deliveryman, 200);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getCurrentOrders(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $deliveryman = $this->deliveryman->where(['auth_token' => $request['token']])->first();
        if (!isset($deliveryman)) {
            return response()->json([
                'errors' => [
                    ['code' => '401', 'message' => 'Invalid token!']
                ]
            ], 401);
        }
        $orders = $this->order->with(['delivery_address','customer', 'partial_payment', 'order_image'])
            ->whereIn('order_status', ['pending', 'confirmed', 'processing', 'out_for_delivery'])
            ->where(['delivery_man_id' => $deliveryman['id']])
            ->get();

        return response()->json($orders, 200);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function recordLocationData(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required',
            'order_id' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $deliveryman = $this->deliveryman->where(['auth_token' => $request['token']])->first();
        if (!isset($deliveryman)) {
            return response()->json([
                'errors' => [
                    ['code' => 'delivery-man', 'message' => 'Invalid token!']
                ]
            ], 401);
        }
        DB::table('delivery_histories')->insert([
            'order_id' => $request['order_id'],
            'deliveryman_id' => $deliveryman['id'],
            'longitude' => $request['longitude'],
            'latitude' => $request['latitude'],
            'time' => now(),
            'location' => $request['location'],
            'created_at' => now(),
            'updated_at' => now()
        ]);
        return response()->json(['message' => 'location recorded'], 200);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getOrderHistory(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required',
            'order_id' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $deliveryman = $this->deliveryman->where(['auth_token' => $request['token']])->first();
        if (!isset($deliveryman)) {
            return response()->json([
                'errors' => [
                    ['code' => 'delivery-man', 'message' => 'Invalid token!']
                ]
            ], 401);
        }

        $history = $this->deliveryHistory->where(['order_id' => $request['order_id'], 'deliveryman_id' => $deliveryman['id']])->get();
        return response()->json($history, 200);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function updateOrderStatus(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required',
            'order_id' => 'required',
            'status' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $deliveryman = $this->deliveryman->where(['auth_token' => $request['token']])->first();
        if (!isset($deliveryman)) {
            return response()->json([
                'errors' => [
                    ['code' => 'delivery-man', 'message' => 'Invalid token!']
                ]
            ], 401);
        }

        $this->order->where(['id' => $request['order_id'], 'delivery_man_id' => $deliveryman['id']])->update([
            'order_status' => $request['status']
        ]);

        $order= $this->order->find($request['order_id']);
        $languageCode = $order->is_guest == 0 ? ($order->customer ? $order->customer->language_code : 'en') : ($order->guest ? $order->guest->language_code : 'en');
        $customerFcmToken = $order->is_guest == 0 ? ($order->customer ? $order->customer->cm_firebase_token : null) : ($order->guest ? $order->guest->fcm_token : null);

        if ($request['status']=='out_for_delivery'){
            $message = Helpers::order_status_update_message('ord_start');

            if ($languageCode != 'en'){
                $message = $this->translate_message($languageCode, 'ord_start');
            }
            $value = $this->dynamic_key_replaced_message(message: $message, type: 'order', order: $order);

        }elseif ($request['status']=='delivered'){
            $message = Helpers::order_status_update_message('delivery_boy_delivered');

            if ($languageCode != 'en'){
                $message = $this->translate_message($languageCode, 'delivery_boy_delivered');
            }
            $value = $this->dynamic_key_replaced_message(message: $message, type: 'order', order: $order);

            if ($order->is_guest == 0){
                if($order->user_id) CustomerLogic::create_loyalty_point_transaction($order->user_id, $order->id, $order->order_amount, 'order_place');

                $user = $this->user->find($order->user_id);
                $isFirstOrder = $this->order->where(['user_id' => $user->id, 'order_status' => 'delivered'])->count('id');
                $referred_by_user = $this->user->find($user->referred_by);

                if ($isFirstOrder < 2 && isset($user->referred_by) && isset($referred_by_user)){
                    if($this->businessSetting->where('key','ref_earning_status')->first()->value == 1) {
                        CustomerLogic::referral_earning_wallet_transaction($order->user_id, 'referral_order_place', $referred_by_user->id);
                    }
                }
            }

            if ($order['payment_method'] == 'cash_on_delivery'){
                $partialData = OrderPartialPayment::where(['order_id' => $order->id])->first();
                if ($partialData){
                    $partial = new OrderPartialPayment;
                    $partial->order_id = $order['id'];
                    $partial->paid_with = 'cash_on_delivery';
                    $partial->paid_amount = $partialData->due_amount;
                    $partial->due_amount = 0;
                    $partial->save();
                }
            }
        }

        try {
            if ($value){
                $data=[
                    'title'=>'Order',
                    'description'=>$value,
                    'order_id'=>$order['id'],
                    'image'=>'',
                    'type' => 'order'
                ];
                Helpers::send_push_notif_to_device($customerFcmToken,$data);
            }
        } catch (\Exception $e) {

        }

        return response()->json(['message' => 'Status updated'], 200);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getOrderDetails(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $deliveryman = $this->deliveryman->where(['auth_token' => $request['token']])->first();
        if (!isset($deliveryman)) {
            return response()->json([
                'errors' => [
                    ['code' => 'delivery-man', 'message' => 'Invalid token!']
                ]
            ], 401);
        }
        $order = $this->order->with(['details'])->where(['delivery_man_id' => $deliveryman['id'], 'id' => $request['order_id']])->first();
        $details = $order->details;
        foreach ($details as $detail) {
            $detail['add_on_ids'] = json_decode($detail['add_on_ids']);
            $detail['add_on_qtys'] = json_decode($detail['add_on_qtys']);

            if (gettype(json_decode($detail['variation'])) == 'array'){
                $variation = json_decode($detail['variation']);
            }else{
                $variation = [];
                $variation[] = json_decode($detail['variation']);

            }
            $detail['variation'] = $variation;

            $detail['formatted_variation'] = $variation[0] ?? null;
            if (isset($variation[0]) && $variation[0]->type == null){
                $detail['formatted_variation'] = null;
            }
            $detail['product_details'] = Helpers::product_data_formatting(json_decode($detail['product_details'], true));
        }
        return response()->json($details, 200);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getAllOrders(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $deliveryman = $this->deliveryman->where(['auth_token' => $request['token']])->first();
        if (!isset($deliveryman)) {
            return response()->json([
                'errors' => [
                    ['code' => '401', 'message' => 'Invalid token!']
                ]
            ], 401);
        }
        $orders = $this->order->with(['delivery_address','customer'])->where(['delivery_man_id' => $deliveryman['id']])->get();
        return response()->json($orders, 200);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getLastLocation(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $lastData = $this->deliveryHistory->where(['order_id' => $request['order_id']])->latest()->first();
        return response()->json($lastData, 200);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function orderPaymentStatusUpdate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $deliveryman = $this->deliveryman->where(['auth_token' => $request['token']])->first();
        if (!isset($deliveryman)) {
            return response()->json([
                'errors' => [
                    ['code' => 'delivery-man', 'message' => 'Invalid token!']
                ]
            ], 401);
        }

        if ($this->order->where(['delivery_man_id' => $deliveryman['id'], 'id' => $request['order_id']])->first()) {
            $this->order->where(['delivery_man_id' => $deliveryman['id'], 'id' => $request['order_id']])->update([
                'payment_status' => $request['status']
            ]);
            return response()->json(['message' => 'Payment status updated'], 200);
        }
        return response()->json([
            'errors' => [
                ['code' => 'order', 'message' => 'not found!']
            ]
        ], 404);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function updateFcmToken(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $deliveryman = $this->deliveryman->where(['auth_token' => $request['token']])->first();
        if (!isset($deliveryman)) {
            return response()->json([
                'errors' => [
                    ['code' => 'delivery-man', 'message' => 'Invalid token!']
                ]
            ], 401);
        }

        $this->deliveryman->where(['id' => $deliveryman['id']])->update([
            'fcm_token' => $request['fcm_token']
        ]);

        return response()->json(['message'=>'successfully updated!'], 200);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function changeLanguage(Request $request): JsonResponse
    {
        $deliveryman = $this->deliveryman->where(['auth_token' => $request['token']])->first();
        if (isset($deliveryman)){
            $deliveryman->language_code = $request->language_code ?? 'en';
            $deliveryman->save();
        }
        return response()->json(['delivery_man' => $deliveryman], 200);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function orderModel(Request $request): JsonResponse
    {
        $deliveryman = $this->deliveryman->where(['auth_token' => $request['token']])->first();

        if (!isset($deliveryman)) {
            return response()->json([
                'errors' => [['code' => 'delivery-man', 'message' => translate('Invalid token!')]]], 401);
        }

        $order = $this->order
            ->with(['customer', 'partial_payment', 'order_image'])
            ->whereIn('order_status', ['pending', 'confirmed', 'processing', 'out_for_delivery'])
            ->where(['delivery_man_id' => $deliveryman['id'], 'id' => $request->id])
            ->first();

        return response()->json($order, 200);
    }
}
