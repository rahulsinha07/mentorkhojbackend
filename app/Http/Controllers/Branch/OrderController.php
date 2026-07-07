<?php

namespace App\Http\Controllers\Branch;

use App\CentralLogics\CustomerLogic;
use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Model\BusinessSetting;
use App\Model\DeliveryMan;
use App\Model\Order;
use App\Model\OrderDetail;
use App\Model\Product;
use App\Models\OfflinePayment;
use App\Models\OrderPartialPayment;
use App\Traits\HelperTrait;
use App\User;
use Box\Spout\Common\Exception\InvalidArgumentException;
use Box\Spout\Common\Exception\IOException;
use Box\Spout\Common\Exception\UnsupportedTypeException;
use Box\Spout\Writer\Exception\WriterNotOpenedException;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Rap2hpoutre\FastExcel\FastExcel;
use Symfony\Component\HttpFoundation\StreamedResponse;
use function App\CentralLogics\translate;

class OrderController extends Controller
{
    use HelperTrait;
    public function __construct(
        private BusinessSetting $businessSetting,
        private DeliveryMan $deliveryman,
        private Order $order,
        private OrderDetail $orderDetail,
        private Product $product,
        private User $user,
    ){}

    /**
     * @param Request $request
     * @param $status
     * @return Factory|View|Application
     */
    public function list(Request $request, $status): View|Factory|Application
    {
        $queryParam = [];
        $search = $request['search'];
        $startDate = $request['start_date'];
        $endDate = $request['end_date'];

        $this->order->where(['checked' => 0, 'branch_id' => auth('branch')->id()])->update(['checked' => 1]);

        if ($status != 'all') {
            $orders = $this->order->with(['customer'])->where(['order_status' => $status, 'branch_id' => auth('branch')->id()])
                ->when((!is_null($startDate) && !is_null($endDate)), function ($query) use ($startDate, $endDate) {
                    return $query->whereDate('created_at', '>=', $startDate)
                        ->whereDate('created_at', '<=', $endDate);
                });

        } else {
            $orders = $this->order->with(['customer'])->where(['branch_id' => auth('branch')->id()])
                ->when((!is_null($startDate) && !is_null($endDate)), function ($query) use ($startDate, $endDate) {
                    return $query->whereDate('created_at', '>=', $startDate)
                        ->whereDate('created_at', '<=', $endDate);
                });
        }
        $queryParam = ['start_date' => $startDate,'end_date' => $endDate ];

        if ($request->has('search')) {
            $key = explode(' ', $request['search']);
            $orders = $this->order->where(['branch_id'=>auth('branch')->id()])->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('id', 'like', "%{$value}%")
                            ->orWhere('order_status', 'like', "%{$value}%")
                            ->orWhere('payment_status', 'like', "%{$value}%")
                            ->orWhere('transaction_reference', 'like', "%{$value}%");
                    }
                });
            $queryParam = ['search' => $request['search']];
        }
        $orders = $orders->notPos()->orderBy('id','desc')->paginate(Helpers::getPagination())->appends($queryParam);


        $orderStatuses = ['pending', 'confirmed', 'processing', 'out_for_delivery', 'delivered', 'canceled', 'returned', 'failed'];
        $countData = [];

        foreach ($orderStatuses as $orderStatus) {
            $countData[$orderStatus] = $this->order->notPos()
                ->where(['order_status' => $orderStatus, 'branch_id' => auth('branch')->id()])
                ->when((!is_null($startDate) && !is_null($endDate)), function ($query) use ($startDate, $endDate) {
                    return $query->whereDate('created_at', '>=', $startDate)
                        ->whereDate('created_at', '<=', $endDate);
                })
                ->count();
        }

        return view('branch-views.order.list', compact('orders', 'status','search', 'countData', 'startDate', 'endDate'));
    }

    /**
     * @param $id
     * @return View|Factory|RedirectResponse|Application
     */
    public function details($id): Factory|View|Application|RedirectResponse
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
     * @param $order
     * @param $amount
     * @return void
     */
    private function calculateRefundAmount($order, $amount): void
    {
        $customer = $this->user->find($order['user_id']);
        $customer->wallet_balance += $amount;
        CustomerLogic::create_wallet_transaction($customer->id, $amount, 'refund', $order['id']);
        $customer->save();
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function status(Request $request): RedirectResponse
    {
        $order = $this->order->where(['id' => $request->id, 'branch_id' => auth('branch')->id()])->first();

        if (in_array($order->order_status, ['returned', 'delivered', 'failed', 'canceled'])) {
            Toastr::warning(translate('you_can_not_change_the_status_of '. $order->order_status .' order'));
            return back();
        }

        if ($request->order_status == 'delivered' && $order['payment_status'] != 'paid') {
            Toastr::warning(translate('you_can_not_delivered_a_order_when_order_status_is_not_paid. please_update_payment_status_first'));
            return back();
        }

        if ($request->order_status == 'delivered' && $order['transaction_reference'] == null && !in_array($order['payment_method'],['cash_on_delivery','wallet', 'offline_payment'])) {
            Toastr::warning(translate('add_your_payment_reference_first'));
            return back();
        }

        if ( ($request->order_status == 'out_for_delivery' || $request->order_status == 'delivered') && $order['delivery_man_id'] == null && $order['order_type'] != 'self_pickup') {
            Toastr::warning(translate('Please assign delivery man first!'));
            return back();
        }

        if (in_array($request['order_status'] , ['returned', 'failed', 'canceled']) && $order['is_guest'] == 0 && isset($order->customer) && Helpers::get_business_settings('wallet_status') == 1) {
            if ($order['payment_method'] == 'wallet_payment' && $order->partial_payment->isEmpty() ){
                $this->calculateRefundAmount(order: $order, amount: $order->order_amount);
            }

            if ($order['payment_method'] != 'cash_on_delivery' && $order['payment_method'] != 'wallet_payment' && $order['payment_method'] != 'offline_payment' && $order->partial_payment->isEmpty()){
                $this->calculateRefundAmount(order: $order, amount: $order->order_amount);
            }

            if ($order['payment_method'] == 'offline_payment' && $order['payment_status'] == 'paid' && $order->partial_payment->isEmpty()){
                $this->calculateRefundAmount(order: $order, amount: $order['order_amount']);
            }

            if ($order->partial_payment->isNotEmpty()){
                $partial_payment_total = $order->partial_payment->sum('paid_amount');
                $this->calculateRefundAmount(order: $order, amount: $partial_payment_total);
            }
        }

        if ($request->order_status == 'returned' || $request->order_status == 'failed' || $request->order_status == 'canceled') {
            foreach ($order->details as $detail) {

                if (!isset($detail->variant)){
                    if ($detail['is_stock_decreased'] == 1) {
                        $product = $this->product->find($detail['product_id']);

                        if($product != null){
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
                    }else{
                        Toastr::warning(translate('Product_deleted'));
                    }
                }
            }
        } else {
            foreach ($order->details as $detail) {
                if (!isset($detail->variant)){
                    if ($detail['is_stock_decreased'] == 0) {
                        $product = $this->product->find($detail['product_id']);
                        if($product != null){
                            foreach ($order->details as $c) {
                                $product = $this->product->find($c['product_id']);
                                $type = json_decode($c['variation'])[0]->type;
                                foreach (json_decode($product['variations'], true) as $var) {
                                    if ($type == $var['type'] && $var['stock'] < $c['quantity']) {
                                        Toastr::error(translate('Stock is insufficient!'));
                                        return back();
                                    }
                                }
                            }

                            $type = json_decode($detail['variation'])[0]->type;
                            $variationStore = [];
                            foreach (json_decode($product['variations'], true) as $var) {
                                if ($type == $var['type']) {
                                    $var['stock'] -= $detail['quantity'];
                                }
                                $variationStore[] = $var;
                            }
                            $this->product->where(['id' => $product['id']])->update([
                                'variations' => json_encode($variationStore),
                                'total_stock' => $product['total_stock'] - $detail['quantity'],
                            ]);
                            $this->orderDetail->where(['id' => $detail['id']])->update([
                                'is_stock_decreased' => 1,
                            ]);
                        }
                        else{
                            Toastr::warning(translate('Product_deleted'));
                        }

                    }
                }
            }
        }


        if ($request->order_status == 'delivered') {
            if ($order->is_guest == 0){
                if($order->user_id) {
                    CustomerLogic::create_loyalty_point_transaction($order->user_id, $order->id, $order->order_amount, 'order_place');
                }

                $user = $this->user->find($order->user_id);
                $isFirstOrder = $this->order->where(['user_id' => $user->id, 'order_status' => 'delivered'])->count('id');
                $referredByUser = $this->user->find($user->referred_by);

                if ($isFirstOrder < 2 && isset($user->referred_by) && isset($referredByUser)){
                    if($this->businessSetting->where('key','ref_earning_status')->first()->value == 1) {
                        CustomerLogic::referral_earning_wallet_transaction($order->user_id, 'referral_order_place', $referredByUser->id);
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

        $order->order_status = $request->order_status;
        $order->save();

        $message = Helpers::order_status_update_message($request->order_status);
        $customerLanguageCode = $order->is_guest == 0 ? ($order->customer ? $order->customer->language_code : 'en') : ($order->guest ? $order->guest->language_code : 'en');
        $customerFcmToken = $order->is_guest == 0 ? ($order->customer ? $order->customer->cm_firebase_token : null) : ($order->guest ? $order->guest->fcm_token : null);

        if ($customerLanguageCode != 'en'){
            $message = $this->translate_message($customerLanguageCode, $request->order_status);
        }
        $value = $this->dynamic_key_replaced_message(message: $message, type: 'order', order: $order);

        try {
            if ($value) {
                $data = [
                    'title' => translate('Order'),
                    'description' => $value,
                    'image' => '',
                    'order_id' => $order->id,
                    'type' => 'order'
                ];
                Helpers::send_push_notif_to_device($customerFcmToken, $data);
            }
        } catch (\Exception $e) {
            Toastr::warning(translate('Push notification failed for Customer!'));
        }

        if ($request->order_status == 'processing' && $order->delivery_man != null) {
            $deliverymanFcmToken = $order->delivery_man->fcm_token;
            $message = Helpers::order_status_update_message('deliveryman_order_processing');
            $deliverymanlanguageCode = $order->delivery_man->language_code ?? 'en';

            if ($deliverymanlanguageCode != 'en'){
                $message = $this->translate_message($deliverymanlanguageCode, 'deliveryman_order_processing');
            }
            $value = $this->dynamic_key_replaced_message(message: $message, type: 'order', order: $order);

            try {
                if ($value) {
                    $data = [
                        'title' => translate('Order'),
                        'description' => $value,
                        'order_id' => $order['id'],
                        'image' => '',
                        'type' => 'order'
                    ];
                    Helpers::send_push_notif_to_device($deliverymanFcmToken, $data);
                }
            } catch (\Exception $e) {
                Toastr::warning(translate('Push notification failed for DeliveryMan!'));
            }
        }

        Toastr::success(translate('Order status updated!'));
        return back();
    }

    /**
     * @param $order_id
     * @param $delivery_man_id
     * @return JsonResponse
     */
    public function addDeliveryman($order_id, $delivery_man_id): JsonResponse
    {
        if ($delivery_man_id == 0) {
            return response()->json([], 401);
        }
        $order = $this->order->where(['id' => $order_id, 'branch_id' => auth('branch')->id()])->first();

        if ($order->order_status == 'pending' || $order->order_status == 'confirmed' || $order->order_status == 'delivered' || $order->order_status == 'returned' || $order->order_status == 'failed' || $order->order_status == 'canceled') {
            return response()->json(['status' => false], 200);
        }

        $order->delivery_man_id = $delivery_man_id;
        $order->save();

        $deliverymanMessage = Helpers::order_status_update_message('del_assign');
        $deliverymanLanguageCode = $order->delivery_man ? $order->delivery_man->language_code : 'en';
        $deliverymanFcmToken = $order->delivery_man ? $order->delivery_man->fcm_token : null;

        if ($deliverymanLanguageCode != 'en'){
            $deliverymanMessage = $this->translate_message($deliverymanLanguageCode, 'del_assign');
        }
        $value = $this->dynamic_key_replaced_message($deliverymanMessage, $order);

        try {
            if ($value) {
                $data = [
                    'title' => translate('Order'),
                    'description' => $value,
                    'order_id' => $order['id'],
                    'image' => '',
                    'type' => 'order'
                ];
                Helpers::send_push_notif_to_device($deliverymanFcmToken, $data);

                $customerNotifyMessage = Helpers::order_status_update_message('customer_notify_message');
                $customerLanguageCode = $order->is_guest == 0 ? ($order->customer ? $order->customer->language_code : 'en') : ($order->guest ? $order->guest->language_code : 'en');
                $customerFcmToken = $order->is_guest == 0 ? ($order->customer ? $order->customer->cm_firebase_token : null) : ($order->guest ? $order->guest->fcm_token : null);

                if ($customerLanguageCode != 'en'){
                    $customerNotifyMessage = $this->translate_message($customerLanguageCode, 'customer_notify_message');
                }
                $value = $this->dynamic_key_replaced_message(message: $customerNotifyMessage, type: 'order', order: $order);

                if($customerNotifyMessage) {
                    $data['description'] = $customerNotifyMessage;
                    Helpers::send_push_notif_to_device($customerFcmToken, $data);
                }
            }
        } catch (\Exception $e) {
            Toastr::warning(translate('Push notification failed for DeliveryMan!'));
        }

        Toastr::success(translate('Deliveryman successfully assigned/changed'));
        return response()->json(['status' => true], 200);
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function paymentStatus(Request $request): RedirectResponse
    {
        $order = $this->order->where(['id' => $request->id, 'branch_id' => auth('branch')->id()])->first();

        if ($order->payment_method == 'offline_payment' && isset($order->offline_payment) && $order->offline_payment?->status != 1){
            Toastr::warning(translate('please_verify_your_offline_payment_verification'));
            return back();
        }
        if ($request->payment_status == 'paid' && $order['transaction_reference'] == null && !in_array($order['payment_method'],['cash_on_delivery','wallet_payment', 'offline_payment'])) {
            Toastr::warning(translate('Add your payment reference code first!'));
            return back();
        }

        if ($request->payment_status == 'paid' && $order['order_status'] == 'pending'){
            $order->order_status = 'confirmed';

            $message = Helpers::order_status_update_message('confirmed');
            $customerLanguageCode = $order->is_guest == 0 ? ($order->customer ? $order->customer->language_code : 'en') : ($order->guest ? $order->guest->language_code : 'en');
            $customerFcmToken = $order->is_guest == 0 ? ($order->customer ? $order->customer->cm_firebase_token : null) : ($order->guest ? $order->guest->fcm_token : null);

            if ($customerLanguageCode != 'en'){
                $message = $this->translate_message($customerLanguageCode, 'confirmed');
            }
            $value = $this->dynamic_key_replaced_message(message: $message, type: 'order', order: $order);

            try {
                if ($value) {
                    $data = [
                        'title' => translate('Order'),
                        'description' => $value,
                        'order_id' => $order['id'],
                        'image' => '',
                        'type' => 'order'
                    ];
                    Helpers::send_push_notif_to_device($customerFcmToken, $data);
                }
            } catch (\Exception $e) {
                //
            }
        }
        $order->payment_status = $request->payment_status;
        $order->save();

        Toastr::success(translate('Payment status updated!'));
        return back();
    }

    /**
     * @param Request $request
     * @param $id
     * @return RedirectResponse
     */
    public function updateShipping(Request $request, $id): RedirectResponse
    {
        $request->validate([
            'contact_person_name' => 'required',
            'address_type' => 'required',
            'contact_person_number' => 'required',
            'address' => 'required'
        ]);

        $address = [
            'contact_person_name' => $request->contact_person_name,
            'contact_person_number' => $request->contact_person_number,
            'address_type' => $request->address_type,
            'address' => $request->address,
            'longitude' => $request->longitude,
            'latitude' => $request->latitude,
            'road' => $request->road,
            'house' => $request->house,
            'floor' => $request->floor,
            'created_at' => now(),
            'updated_at' => now()
        ];

        DB::table('customer_addresses')->where('id', $id)->update($address);
        Toastr::success(translate('Payment status updated!'));
        return back();
    }

    /**
     * @param $id
     * @return Factory|View|Application
     */
    public function generateInvoice($id): View|Factory|Application
    {
        $order = $this->order->where(['id' => $id, 'branch_id' => auth('branch')->id()])->first();
        return view('branch-views.order.invoice', compact('order'));
    }

    /**
     * @param Request $request
     * @param $id
     * @return RedirectResponse
     */
    public function addPaymentReferenceCode(Request $request, $id): RedirectResponse
    {
        $this->order->where(['id' => $id, 'branch_id' => auth('branch')->id()])->update([
            'transaction_reference' => $request['transaction_reference']
        ]);

        Toastr::success(translate('Payment reference code is added!'));
        return back();
    }

    /**
     * @param Request $request
     * @return JsonResponse|void
     */
    public function updateTimeSlot(Request $request)
    {
        if ($request->ajax()) {
            $order = $this->order->find($request->id);
            $order->time_slot_id = $request->timeSlot;
            $order->save();
            $data = $request->timeSlot;

            return response()->json($data);
        }
    }

    /**
     * @param Request $request
     * @return JsonResponse|void
     */
    public function updateDeliveryDate(Request $request)
    {
        if ($request->ajax()) {
            $order = $this->order->find($request->id);
            $order->delivery_date = $request->deliveryDate;
            $order->save();
            $data = $request->deliveryDate;
            return response()->json($data);
        }
    }

    /**
     * @param Request $request
     * @param $status
     * @return StreamedResponse|string
     * @throws IOException
     * @throws InvalidArgumentException
     * @throws UnsupportedTypeException
     * @throws WriterNotOpenedException
     */
    public function exportOrders(Request $request, $status): StreamedResponse|string
    {
        $queryParam = [];
        $search = $request['search'];
        $startDate = $request['start_date'];
        $endDate = $request['end_date'];

        if ($status != 'all') {
            $orders = $this->order->with(['customer'])->where(['order_status' => $status, 'branch_id' => auth('branch')->id()])
                ->when((!is_null($startDate) && !is_null($endDate)), function ($query) use ($startDate, $endDate) {
                    return $query->whereDate('created_at', '>=', $startDate)
                        ->whereDate('created_at', '<=', $endDate);
                });
        } else {
            $orders = $this->order->with(['customer'])->where(['branch_id' => auth('branch')->id()])
                ->when((!is_null($startDate) && !is_null($endDate)), function ($query) use ($startDate, $endDate) {
                    return $query->whereDate('created_at', '>=', $startDate)
                        ->whereDate('created_at', '<=', $endDate);
                });
        }

        if ($request->has('search')) {
            $key = explode(' ', $request['search']);
            $orders = $this->order->where(['branch_id'=>auth('branch')->id()])->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('id', 'like', "%{$value}%")
                        ->orWhere('order_status', 'like', "%{$value}%")
                        ->orWhere('payment_status', 'like', "%{$value}%")
                        ->orWhere('transaction_reference', 'like', "%{$value}%");
                }
            });
            $queryParam = ['search' => $request['search']];
        }

        $orders = $orders->notPos()->orderBy('id','desc')->get();

        $storage = [];
        foreach($orders as $order){
            $branch = $order->branch ? $order->branch->name : '';
            $customer = $order->customer ? $order->customer->f_name .' '. $order->customer->l_name : '';
            $deliveryman = $order->delivery_man ? $order->delivery_man->f_name .' '. $order->delivery_man->l_name : '';
            $timeslot = $order->time_slot ? $order->time_slot->start_time .' - '. $order->time_slot->end_time : '';

            $storage[] = [
                'order_id' => $order['id'],
                'customer' => $customer,
                'order_amount' => $order['order_amount'],
                'coupon_discount_amount' => $order['coupon_discount_amount'],
                'payment_status' => $order['payment_status'],
                'order_status' => $order['order_status'],
                'total_tax_amount'=>$order['total_tax_amount'],
                'payment_method' => $order['payment_method'],
                'transaction_reference' => $order['transaction_reference'],
                'delivery_man' => $deliveryman,
                'delivery_charge' => $order['delivery_charge'],
                'coupon_code' => $order['coupon_code'],
                'order_type' => $order['order_type'],
                'branch'=>  $branch,
                'time_slot_id' => $timeslot,
                'date' => $order['date'],
                'delivery_date' => $order['delivery_date'],
                'extra_discount' => $order['extra_discount'],
            ];
        }
        return (new FastExcel($storage))->download('orders.xlsx');
    }

    /**
     * @param $order_id
     * @param $status
     * @return JsonResponse
     */
    public function VerifyOfflinePayment($order_id, $status): JsonResponse
    {
        $offlineData = OfflinePayment::where(['order_id' => $order_id])->first();

        if (!isset($offlineData)){
            return response()->json(['status' => false, 'message'=> translate('offline data not found')], 200);
        }

        $order = Order::find($order_id);
        if (!isset($order)){
            return response()->json(['status' => false, 'message'=> translate('order not found')], 200);
        }

        if ($status == 1){
            if($order->order_status == 'canceled'){
                return response()->json(['status' => false, 'message'=> translate('Canceled order can not be verified')], 200);
            }

            $offlineData->status = $status;
            $offlineData->save();

            $order->order_status = 'confirmed';
            $order->payment_status = 'paid';
            $order->save();

            $message = Helpers::order_status_update_message('confirmed');
            $customerLanguageCode = $order->is_guest == 0 ? ($order->customer ? $order->customer->language_code : 'en') : ($order->guest ? $order->guest->language_code : 'en');
            $customerFcmToken = $order->is_guest == 0 ? ($order->customer ? $order->customer->cm_firebase_token : null) : ($order->guest ? $order->guest->fcm_token : null);

            if ($customerLanguageCode != 'en'){
                $message = $this->translate_message($customerLanguageCode, 'confirmed');
            }
            $value = $this->dynamic_key_replaced_message(message: $message, type: 'order', order: $order);

            try {
                if ($value) {
                    $data = [
                        'title' => translate('Order'),
                        'description' => $value,
                        'order_id' => $order['id'],
                        'image' => '',
                        'type' => 'order'
                    ];
                    Helpers::send_push_notif_to_device($customerFcmToken, $data);
                }
            } catch (\Exception $e) {
                //
            }

        }elseif ($status == 2){
            $offlineData->status = $status;
            $offlineData->save();

            $customerFcmToken = null;
            if($order->is_guest == 0){
                $customerFcmToken = $order->customer ? $order->customer->cm_firebase_token : null;
            }elseif($order->is_guest == 1){
                $customerFcmToken = $order->guest ? $order->guest->fcm_token : null;
            }
            if ($customerFcmToken != null) {
                try {
                    $data = [
                        'title' => translate('Order'),
                        'description' => translate('Offline payment is not verified'),
                        'order_id' => $order->id,
                        'image' => '',
                        'type' => 'order',
                    ];
                    Helpers::send_push_notif_to_device($customerFcmToken, $data);
                } catch (\Exception $e) {
                }
            }
        }
        return response()->json(['status' => true], 200);
    }

    /**
     * @param Request $request
     * @param $status
     * @return Application|Factory|View
     */
    public function offlinePaymentList(Request $request, $status): Factory|View|Application
    {
        $search = $request['search'];
        $statusMapping = [
            'pending' => 0,
            'denied' => 2,
        ];

        $status = $statusMapping[$status];

        $orders = $this->order->with(['offline_payment'])
            ->where(['branch_id' => auth('branch')->id()])
            ->where(['payment_method' => 'offline_payment'])
            ->whereHas('offline_payment', function ($query) use($status){
                $query->where('status', $status);
            })
            ->when($request->has('search'), function ($query) use ($request) {
                $keys = explode(' ', $request['search']);
                return $query->where(function ($query) use ($keys) {
                    foreach ($keys as $key) {
                        $query->where('id', 'LIKE', '%' . $key . '%');
                    }
                });
            })
            ->latest()
            ->paginate(Helpers::getPagination());

        return view('branch-views.order.offline-payment.list', compact('orders', 'search'));
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function offlineQuickViewDetails(Request $request): JsonResponse
    {
        $order = $this->order->find($request->id);

        return response()->json([
            'view' => view('branch-views.order.offline-payment.details-quick-view', compact('order'))->render(),
        ]);
    }
}
