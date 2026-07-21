<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\RazorpaySeminarService;
use Illuminate\Http\JsonResponse;

class PaymentGatewayController extends Controller
{
    public function __construct(private RazorpaySeminarService $razorpay) {}

    public function razorPayPublicKey(): JsonResponse
    {
        $keyId = $this->razorpay->keyId();
        if (!$keyId) {
            return response()->json([
                'ok' => false,
                'message' => 'RazorPay not configured in admin payment settings.',
            ], 503);
        }

        return response()->json([
            'ok' => true,
            'gateway' => 'razor_pay',
            'key_id' => $keyId,
        ]);
    }
}
