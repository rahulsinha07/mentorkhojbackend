<?php

namespace App\Http\Controllers\Api\V1\Mentor;

use App\CentralLogics\Helpers;
use App\CentralLogics\MentorBookingLogic;
use App\CentralLogics\MentorBookingMailLogic;
use App\CentralLogics\MentorLogic;
use App\Http\Controllers\Controller;
use App\Model\Branch;
use App\Model\Mentor\Mentor;
use App\Model\Mentor\MentorBooking;
use App\Model\Mentor\MentorService;
use App\Model\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MentorBookingController extends Controller
{
    public function book(Request $request, int $id): JsonResponse
    {
        $mentor = Mentor::published()->find($id);
        if (!$mentor) {
            return response()->json(['errors' => [['message' => 'Mentor not found']]], 404);
        }

        $validator = Validator::make($request->all(), [
            'service_id' => 'required|integer|exists:mentor_services,id',
            'preferred_date' => 'nullable|date',
            'preferred_time' => 'nullable|string',
            'mentee_note' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $service = MentorService::where('mentor_id', $mentor->id)
            ->where('id', $request->service_id)
            ->where('is_enabled', true)
            ->first();

        if (!$service) {
            return response()->json(['errors' => [['message' => 'Service not available']]], 404);
        }

        $menteeId = $request->user()?->id;

        if ($menteeId) {
            $existing = MentorBookingLogic::findRetryableBooking($menteeId, $mentor->id, $service->id);
            if ($existing) {
                if ($existing->payment_status === 'failed') {
                    $existing = MentorBookingLogic::prepareForPaymentRetry($existing);
                }

                $existing->update([
                    'preferred_date' => $request->preferred_date ?? $existing->preferred_date,
                    'preferred_time' => $request->preferred_time ?? $existing->preferred_time,
                    'mentee_note' => $request->mentee_note ?? $existing->mentee_note,
                ]);

                return response()->json([
                    'message' => $existing->payment_status === 'pending'
                        ? 'Existing booking resumed. Complete payment to confirm.'
                        : 'Booking request created',
                    'booking' => MentorBookingLogic::formatBooking($existing->fresh()),
                ], 200);
            }
        }

        $booking = MentorBookingLogic::createBooking($mentor, $service, $menteeId, $request->all());

        return response()->json([
            'message' => 'Booking request created',
            'booking' => MentorBookingLogic::formatBooking($booking),
        ], 201);
    }

    public function myBookings(Request $request): JsonResponse
    {
        MentorBookingLogic::syncPendingPaidBookingsForUser((int) $request->user()->id);

        $bookings = MentorBooking::where('mentee_user_id', $request->user()->id)
            ->with(['service', 'mentor'])
            ->latest()
            ->paginate(20);

        return response()->json([
            'bookings' => collect($bookings->items())->map(fn ($b) => MentorBookingLogic::formatBooking($b)),
            'total' => $bookings->total(),
        ]);
    }

    public function showMyBooking(Request $request, int $id): JsonResponse
    {
        MentorBookingLogic::syncPendingPaidBookingsForUser((int) $request->user()->id);

        $booking = MentorBooking::with(['service', 'mentor'])
            ->where('mentee_user_id', $request->user()->id)
            ->find($id);

        if (!$booking) {
            return response()->json(['errors' => [['message' => 'Booking not found']]], 404);
        }

        return response()->json([
            'booking' => MentorBookingLogic::formatBooking($booking),
        ]);
    }

    public function verifyPayment(Request $request, int $id): JsonResponse
    {
        $booking = MentorBooking::with(['service', 'mentor'])->find($id);
        if (!$booking || (int) $booking->mentee_user_id !== (int) $request->user()->id) {
            return response()->json(['errors' => [['message' => 'Booking not found']]], 404);
        }

        if ($booking->payment_status === 'paid') {
            MentorBookingMailLogic::maybeSendAfterPayment($booking->fresh());

            return response()->json([
                'ok' => true,
                'message' => 'Payment confirmed! Your session is booked.',
                'booking' => MentorBookingLogic::formatBooking($booking->fresh(['service', 'mentor'])),
            ]);
        }

        $validated = $request->validate([
            'razorpay_order_id' => 'required_without:transaction_reference|nullable|string',
            'razorpay_payment_id' => 'required_with:razorpay_order_id|nullable|string',
            'razorpay_signature' => 'required_with:razorpay_order_id|nullable|string',
            'payment_method' => 'required_with:transaction_reference|nullable|string',
            'transaction_reference' => 'required_without:razorpay_order_id|nullable|string',
        ]);

        try {
            $booking = MentorBookingLogic::verifyAndMarkPaid($booking, $validated);
        } catch (\RuntimeException $e) {
            $booking = MentorBookingLogic::markPaymentFailed(
                $booking,
                $e->getMessage(),
            );

            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
                'booking' => MentorBookingLogic::formatBooking($booking),
                'can_retry_payment' => true,
            ], 400);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Payment confirmed! Your session is booked.',
            'booking' => MentorBookingLogic::formatBooking($booking->fresh(['service', 'mentor'])),
        ]);
    }

    public function mentorBookings(Request $request): JsonResponse
    {
        $mentor = Mentor::where('user_id', $request->user()->id)->first();
        if (!$mentor) {
            return response()->json(['errors' => [['message' => 'Mentor profile not found']]], 404);
        }

        $bookings = MentorBooking::where('mentor_id', $mentor->id)
            ->with(['service', 'mentee'])
            ->latest()
            ->paginate(20);

        return response()->json([
            'bookings' => collect($bookings->items())->map(fn ($b) => MentorBookingLogic::formatBooking($b)),
            'total' => $bookings->total(),
        ]);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $mentor = Mentor::where('user_id', $request->user()->id)->first();
        if (!$mentor) {
            return response()->json(['errors' => [['message' => 'Mentor profile not found']]], 404);
        }

        $booking = MentorBooking::where('mentor_id', $mentor->id)->where('id', $id)->first();
        if (!$booking) {
            return response()->json(['errors' => [['message' => 'Booking not found']]], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:requested,confirmed,completed,cancelled,refunded',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $previousStatus = $booking->status;
        $booking->status = $request->status;
        $booking->save();

        if ($previousStatus !== 'confirmed' && $booking->status === 'confirmed') {
            MentorBookingMailLogic::sendMenteeConfirmedEmail($booking);
        }

        return response()->json([
            'message' => 'Booking updated',
            'booking' => MentorBookingLogic::formatBooking($booking),
        ]);
    }

    public function checkoutContext(Request $request, int $id): JsonResponse
    {
        $booking = MentorBooking::with(['service', 'mentor'])->find($id);
        if (!$booking || (int) $booking->mentee_user_id !== (int) $request->user()->id) {
            return response()->json(['errors' => [['message' => 'Booking not found']]], 404);
        }

        if ($booking->payment_status === 'paid') {
            return response()->json([
                'errors' => [['message' => 'This booking is already paid.']],
                'booking' => MentorBookingLogic::formatBooking($booking),
            ], 400);
        }

        if ($booking->payment_status === 'failed') {
            try {
                $booking = MentorBookingLogic::prepareForPaymentRetry($booking);
            } catch (\InvalidArgumentException $e) {
                return response()->json(['errors' => [['message' => $e->getMessage()]]], 400);
            }
        }

        if ($booking->payment_status !== 'pending') {
            return response()->json(['errors' => [['message' => 'Payment is not available for this booking.']]], 400);
        }

        $mentor = $booking->mentor;
        $legacyProductId = $mentor?->legacy_product_id;
        $product = $legacyProductId ? Product::find($legacyProductId) : null;
        $branch = Branch::active()->first();

        return response()->json([
            'booking' => MentorBookingLogic::formatBooking($booking),
            'legacy_product_id' => $legacyProductId,
            'variation_type' => $booking->service
                ? preg_replace('/\s+/', '', $booking->service->title)
                : null,
            'product' => $product ? [
                'id' => $product->id,
                'name' => $product->name,
                'price' => $product->price,
                'tax' => $product->tax,
                'tax_type' => $product->tax_type,
            ] : null,
            'branch_id' => $branch?->id,
            'wallet_balance' => (float) ($request->user()->wallet_balance ?? 0),
        ]);
    }

    public function reportPaymentFailure(Request $request, int $id): JsonResponse
    {
        $booking = MentorBooking::with(['service', 'mentor'])->find($id);
        if (!$booking || (int) $booking->mentee_user_id !== (int) $request->user()->id) {
            return response()->json(['errors' => [['message' => 'Booking not found']]], 404);
        }

        if ($booking->payment_status === 'paid') {
            return response()->json(['errors' => [['message' => 'This booking is already paid.']]], 400);
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $booking = MentorBookingLogic::markPaymentFailed(
            $booking,
            $validated['reason'] ?? 'Payment was not completed.',
        );

        return response()->json([
            'message' => 'Payment was not completed. Your session is not confirmed yet. You can try again.',
            'booking' => MentorBookingLogic::formatBooking($booking),
        ], 200);
    }
}
