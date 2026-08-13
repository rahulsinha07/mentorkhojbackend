<?php

namespace App\Http\Controllers;

use App\Models\PaymentRequest;
use App\User;
use App\Traits\Processor;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Razorpay\Api\Api;

class RazorPayController extends Controller
{
    use Processor;

    private PaymentRequest $payment;
    private $user;

    public function __construct(PaymentRequest $payment, User $user)
    {
        $config = $this->payment_config('razor_pay', 'payment_config');
        $razor = false;
        if (!is_null($config) && $config->mode == 'live') {
            $razor = json_decode($config->live_values);
        } elseif (!is_null($config) && $config->mode == 'test') {
            $razor = json_decode($config->test_values);
        }

        if ($razor) {
            $config = array(
                'api_key' => $razor->api_key,
                'api_secret' => $razor->api_secret
            );
            Config::set('razor_config', $config);
        }

        $this->payment = $payment;
        $this->user = $user;
    }

    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_id' => 'required|uuid'
        ]);

        if ($validator->fails()) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_400, null, $this->error_processor($validator)), 400);
        }

        $data = $this->payment::where(['id' => $request['payment_id']])->where(['is_paid' => 0])->first();
        if (!isset($data)) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_204), 200);
        }
        $payer = json_decode($data['payer_information']);

        if ($data['additional_data'] != null) {
            $business = json_decode($data['additional_data']);
            $business_name = $business->business_name ?? "my_business";
            $business_logo = $business->business_logo ?? url('/');
        } else {
            $business_name = "my_business";
            $business_logo = url('/');
        }

        return view('payment-gateway.razor-pay', compact('data', 'payer', 'business_logo', 'business_name'));
    }

    public function payment(Request $request): JsonResponse|Redirector|RedirectResponse|Application
    {
        $input = $request->all();
        $paymentRow = $this->payment::where(['id' => $request['payment_id']])->first();

        if (empty($input['razorpay_payment_id'])) {
            if ($paymentRow && function_exists($paymentRow->failure_hook)) {
                call_user_func($paymentRow->failure_hook, $paymentRow);
            }

            return $this->payment_response($paymentRow, 'fail');
        }

        try {
            $apiKey = config('razor_config.api_key');
            $apiSecret = config('razor_config.api_secret');
            if (!$apiKey || !$apiSecret) {
                throw new \RuntimeException('RazorPay is not configured.');
            }

            $api = new Api($apiKey, $apiSecret);
            $payment = $api->payment->fetch($input['razorpay_payment_id']);
            $status = (string) ($payment['status'] ?? '');

            if ($status === 'authorized') {
                $api->payment->fetch($input['razorpay_payment_id'])->capture(['amount' => $payment['amount']]);
            } elseif ($status !== 'captured') {
                throw new \RuntimeException('Payment was not completed (status: ' . $status . ').');
            }

            $this->payment::where(['id' => $request['payment_id']])->update([
                'payment_method' => 'razor_pay',
                'is_paid' => 1,
                'transaction_id' => $input['razorpay_payment_id'],
            ]);

            $data = $this->payment::where(['id' => $request['payment_id']])->first();
            if (isset($data) && function_exists($data->success_hook)) {
                call_user_func($data->success_hook, $data);
            }

            return $this->payment_response($data, 'success');
        } catch (\Throwable $e) {
            Log::error('RazorPay payment callback failed', [
                'payment_id' => $request['payment_id'],
                'razorpay_payment_id' => $input['razorpay_payment_id'] ?? null,
                'error' => $e->getMessage(),
            ]);

            if ($paymentRow && function_exists($paymentRow->failure_hook)) {
                call_user_func($paymentRow->failure_hook, $paymentRow);
            }

            return $this->payment_response($paymentRow, 'fail');
        }
    }
}
