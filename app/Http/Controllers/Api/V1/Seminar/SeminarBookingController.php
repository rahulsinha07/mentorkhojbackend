<?php

namespace App\Http\Controllers\Api\V1\Seminar;

use App\CentralLogics\SeminarLogic;
use App\Http\Controllers\Controller;
use App\Model\Seminar\Seminar;
use App\Model\Seminar\SeminarBooking;
use App\Services\RazorpaySeminarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SeminarBookingController extends Controller
{
    public function __construct(private RazorpaySeminarService $razorpay) {}

    public function book(Request $request, string $slug): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['ok' => false, 'message' => 'Login required to book this seminar.'], 401);
        }

        $seminar = SeminarLogic::resolveBySlug($slug);
        if (!$seminar || !$seminar->is_published || $seminar->status === 'draft') {
            return response()->json(['errors' => [['code' => 'not_found', 'message' => 'Seminar not found']]], 404);
        }

        if ($seminar->status === 'paused') {
            return response()->json([
                'ok' => false,
                'message' => 'Registration for this seminar is currently paused.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => ['required', 'string', 'max:32', 'regex:/^(\+91[6-9]\d{9}|\+[1-9]\d{9,14}|[6-9]\d{9})$/'],
            'org' => 'required|string|max:255',
            'details' => 'nullable|string|max:2000',
        ]);

        $fee = (float) ($seminar->fee_amount ?? 0);
        $isFree = $fee <= 0;
        $email = strtolower($validated['email']);

        $existingPaid = SeminarBooking::where('seminar_id', $seminar->id)
            ->where('email', $email)
            ->whereIn('payment_status', ['paid', 'not_required'])
            ->first();

        if ($existingPaid) {
            return response()->json([
                'ok' => false,
                'already_booked' => true,
                'message' => 'You have already registered for this seminar with this email.',
            ], 409);
        }

        $pending = SeminarBooking::where('seminar_id', $seminar->id)
            ->where('email', $email)
            ->where('payment_status', 'pending')
            ->first();

        if ($pending) {
            return response()->json($this->bookingResponse($pending, $seminar));
        }

        $failed = SeminarBooking::where('seminar_id', $seminar->id)
            ->where('email', $email)
            ->where('payment_status', 'failed')
            ->first();

        if ($failed) {
            $failed = $this->razorpay->prepareForPaymentRetry($failed);

            return response()->json($this->bookingResponse($failed, $seminar));
        }

        $booking = SeminarBooking::create([
            'booking_ref' => SeminarBooking::generateBookingRef(),
            'seminar_id' => $seminar->id,
            'customer_id' => $user->id,
            'name' => trim($validated['name']),
            'email' => $email,
            'phone' => trim($validated['phone']),
            'org' => trim($validated['org']),
            'details' => isset($validated['details']) ? trim((string) $validated['details']) : null,
            'amount' => $fee,
            'currency' => $seminar->currency ?? 'INR',
            'status' => $isFree ? 'confirmed' : 'pending',
            'payment_status' => $isFree ? 'not_required' : 'pending',
        ]);

        if ($isFree) {
            $this->razorpay->sendConfirmationEmail($booking);
        }

        return response()->json($this->bookingResponse($booking->fresh(), $seminar));
    }

    public function createPaymentOrder(Request $request, int $id): JsonResponse
    {
        $booking = SeminarBooking::with('seminar')->findOrFail($id);

        if (!$request->user() || $booking->customer_id !== $request->user()->id) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized.'], 403);
        }

        if ($booking->payment_status === 'paid') {
            return response()->json(['ok' => false, 'message' => 'Already paid.'], 400);
        }

        if (!in_array($booking->payment_status, ['pending', 'failed'], true)) {
            return response()->json(['ok' => false, 'message' => 'Payment not required.'], 400);
        }

        try {
            $booking = $this->razorpay->prepareForPaymentRetry($booking);
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 400);
        }

        $order = $this->razorpay->createOrder($booking);

        return response()->json([
            'ok' => true,
            'key_id' => $this->razorpay->keyId(),
            'razorpay_order_id' => $order['id'],
            'amount' => (int) $order['amount'],
            'currency' => $order['currency'],
            'booking_ref' => $booking->booking_ref,
            'prefill' => [
                'name' => $booking->name,
                'email' => $booking->email,
                'contact' => $booking->phone,
            ],
        ]);
    }

    public function verifyPayment(Request $request, int $id): JsonResponse
    {
        $booking = SeminarBooking::with('seminar')->findOrFail($id);

        if (!$request->user() || $booking->customer_id !== $request->user()->id) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'razorpay_order_id' => 'required_without:transaction_reference|nullable|string',
            'razorpay_payment_id' => 'required_with:razorpay_order_id|nullable|string',
            'razorpay_signature' => 'required_with:razorpay_order_id|nullable|string',
            'payment_method' => 'required_with:transaction_reference|nullable|string',
            'transaction_reference' => 'required_without:razorpay_order_id|nullable|string',
        ]);

        try {
            if (!empty($validated['transaction_reference'])) {
                $booking = $this->razorpay->markPaidFromTransactionReference(
                    $booking,
                    $validated['transaction_reference'],
                );
            } else {
                $booking = $this->razorpay->markPaid(
                    $booking,
                    $validated['razorpay_order_id'],
                    $validated['razorpay_payment_id'],
                    $validated['razorpay_signature'],
                );
            }
        } catch (\RuntimeException $e) {
            $booking = $this->razorpay->markPaymentFailed($booking, $e->getMessage());

            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
                'booking_id' => $booking->id,
                'booking_ref' => $booking->booking_ref,
                'payment_status' => $booking->payment_status,
                'status' => $booking->status,
                'can_retry_payment' => true,
            ], 400);
        }

        return response()->json([
            'ok' => true,
            'booking_id' => $booking->id,
            'booking_ref' => $booking->booking_ref,
            'payment_status' => 'paid',
            'status' => 'confirmed',
            'email_sent' => (bool) $booking->email_sent_at,
            'message' => 'Payment confirmed! Your seat is reserved.',
        ]);
    }

    public function reportPaymentFailure(Request $request, int $id): JsonResponse
    {
        $booking = SeminarBooking::with('seminar')->findOrFail($id);

        if (!$request->user() || $booking->customer_id !== $request->user()->id) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized.'], 403);
        }

        if ($booking->payment_status === 'paid') {
            return response()->json(['ok' => false, 'message' => 'This booking is already paid.'], 400);
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $booking = $this->razorpay->markPaymentFailed(
            $booking,
            $validated['reason'] ?? 'Payment was not completed.',
        );

        return response()->json([
            'ok' => false,
            'booking_id' => $booking->id,
            'booking_ref' => $booking->booking_ref,
            'payment_status' => $booking->payment_status,
            'status' => $booking->status,
            'can_retry_payment' => true,
            'message' => 'Payment was not completed. Your seat is not confirmed yet. You can try again.',
        ], 200);
    }

    public function myBookings(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized.'], 401);
        }

        $bookings = SeminarBooking::with('seminar')
            ->where('customer_id', $user->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (SeminarBooking $b) => $this->serializeBooking($b));

        return response()->json(['ok' => true, 'bookings' => $bookings]);
    }

    private function bookingResponse(SeminarBooking $booking, Seminar $seminar): array
    {
        $isFree = (float) $booking->amount <= 0;
        $requiresPayment = !$isFree && $booking->payment_status === 'pending';
        $canRetryPayment = !$isFree && $booking->payment_status === 'failed';

        return [
            'ok' => true,
            'booking_id' => $booking->id,
            'booking_ref' => $booking->booking_ref,
            'payment_status' => $booking->payment_status,
            'status' => $booking->status,
            'amount' => (float) $booking->amount,
            'currency' => $booking->currency,
            'requires_payment' => $requiresPayment,
            'can_retry_payment' => $canRetryPayment,
            'is_free' => $isFree,
            'email_sent' => (bool) $booking->email_sent_at,
            'seminar_title' => $seminar->title,
            'seminar_slug' => $seminar->slug,
            'message' => match ($booking->payment_status) {
                'paid', 'not_required' => 'Thank you for registering. Check your email for confirmation.',
                'failed' => 'Payment failed or was cancelled. You can retry payment to confirm your seat.',
                default => 'Registration saved. Complete payment to confirm your seat.',
            },
        ];
    }

    private function serializeBooking(SeminarBooking $b): array
    {
        $isFree = (float) $b->amount <= 0;

        return [
            'id' => $b->id,
            'booking_ref' => $b->booking_ref,
            'seminar_title' => $b->seminar?->title,
            'seminar_slug' => $b->seminar?->slug,
            'amount' => (float) $b->amount,
            'currency' => $b->currency,
            'status' => $b->status,
            'payment_status' => $b->payment_status,
            'requires_payment' => !$isFree && $b->payment_status === 'pending',
            'can_retry_payment' => !$isFree && $b->payment_status === 'failed',
            'paid_at' => $b->paid_at?->toIso8601String(),
            'created_at' => $b->created_at?->toIso8601String(),
        ];
    }
}
