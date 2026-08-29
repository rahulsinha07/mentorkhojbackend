<?php

namespace App\Http\Controllers\Api\V1\Mentor;

use App\CentralLogics\Helpers;
use App\CentralLogics\MentorBookingLogic;
use App\CentralLogics\MentorBookingMailLogic;
use App\CentralLogics\MentorLegacyProductLogic;
use App\CentralLogics\MentorLogic;
use App\CentralLogics\SessionCreditLogic;
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

        $payableError = self::paidBookingProductError($mentor, $service);
        if ($payableError) {
            return response()->json(['errors' => [['message' => $payableError]]], 422);
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

        $query = MentorBooking::where('mentee_user_id', $request->user()->id)
            ->with(['service', 'mentor']);
        SessionCreditLogic::applyBucketFilter($query, $request->query('bucket'));

        $bookings = $query->latest()->paginate(20);

        return response()->json([
            'bookings' => collect($bookings->items())->map(fn ($b) => MentorBookingLogic::formatBooking($b)),
            'total' => $bookings->total(),
            'bucket' => $request->query('bucket'),
        ]);
    }

    public function mySessionCredits(Request $request): JsonResponse
    {
        return response()->json([
            'credits' => SessionCreditLogic::creditsForMentee((int) $request->user()->id)->values(),
        ]);
    }

    public function updateMySchedule(Request $request, int $id): JsonResponse
    {
        $booking = MentorBooking::with(['service', 'mentor', 'mentee'])
            ->where('mentee_user_id', $request->user()->id)
            ->find($id);

        if (!$booking) {
            return response()->json(['errors' => [['message' => 'Booking not found']]], 404);
        }

        return $this->applyScheduleUpdate($request, $booking, false);
    }

    public function completeMyBooking(Request $request, int $id): JsonResponse
    {
        $booking = MentorBooking::with(['service', 'mentor', 'mentee'])
            ->where('mentee_user_id', $request->user()->id)
            ->find($id);

        if (!$booking) {
            return response()->json(['errors' => [['message' => 'Booking not found']]], 404);
        }

        try {
            $booking = SessionCreditLogic::markComplete($booking);
        } catch (\RuntimeException $e) {
            return response()->json(['errors' => [['message' => $e->getMessage()]]], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Session marked complete.',
            'booking' => MentorBookingLogic::formatBooking($booking),
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

        $query = MentorBooking::where('mentor_id', $mentor->id)
            ->with(['service', 'mentee']);
        SessionCreditLogic::applyBucketFilter($query, $request->query('bucket'));

        $bookings = $query->latest()->paginate(20);

        return response()->json([
            'bookings' => collect($bookings->items())->map(fn ($b) => MentorBookingLogic::formatBooking($b, true)),
            'demo_assignments' => \App\CentralLogics\SessionChatLogic::assignmentsForMentor($mentor),
            'total' => $bookings->total(),
            'bucket' => $request->query('bucket'),
        ]);
    }

    public function mentorSessionCredits(Request $request): JsonResponse
    {
        $mentor = Mentor::where('user_id', $request->user()->id)->first();
        if (!$mentor) {
            return response()->json(['errors' => [['message' => 'Mentor profile not found']]], 404);
        }

        return response()->json([
            'credits' => SessionCreditLogic::creditsForMentor((int) $mentor->id)->values(),
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

        if ($request->status === 'completed') {
            try {
                $booking = SessionCreditLogic::markComplete($booking);
            } catch (\RuntimeException $e) {
                return response()->json(['errors' => [['message' => $e->getMessage()]]], 422);
            }

            return response()->json([
                'message' => 'Booking updated',
                'booking' => MentorBookingLogic::formatBooking($booking, true),
            ]);
        }

        $booking->status = $request->status;
        $booking->save();

        return response()->json([
            'message' => 'Booking updated',
            'booking' => MentorBookingLogic::formatBooking($booking->fresh(['service', 'mentee', 'mentor']), true),
        ]);
    }

    public function completeMentorBooking(Request $request, int $id): JsonResponse
    {
        $mentor = Mentor::where('user_id', $request->user()->id)->first();
        if (!$mentor) {
            return response()->json(['errors' => [['message' => 'Mentor profile not found']]], 404);
        }

        $booking = MentorBooking::where('mentor_id', $mentor->id)->where('id', $id)->first();
        if (!$booking) {
            return response()->json(['errors' => [['message' => 'Booking not found']]], 404);
        }

        try {
            $booking = SessionCreditLogic::markComplete($booking);
        } catch (\RuntimeException $e) {
            return response()->json(['errors' => [['message' => $e->getMessage()]]], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Session marked complete.',
            'booking' => MentorBookingLogic::formatBooking($booking, true),
        ]);
    }

    public function updateSchedule(Request $request, int $id): JsonResponse
    {
        $mentor = Mentor::where('user_id', $request->user()->id)->first();
        if (!$mentor) {
            return response()->json(['errors' => [['message' => 'Mentor profile not found']]], 404);
        }

        $booking = MentorBooking::where('mentor_id', $mentor->id)->where('id', $id)->first();
        if (!$booking) {
            return response()->json(['errors' => [['message' => 'Booking not found']]], 404);
        }

        return $this->applyScheduleUpdate($request, $booking, true);
    }

    private function applyScheduleUpdate(Request $request, MentorBooking $booking, bool $forMentor): JsonResponse
    {
        if (!SessionCreditLogic::canReschedule($booking)) {
            return response()->json(['errors' => [['message' => 'Only upcoming sessions can be rescheduled']]], 422);
        }

        if (!in_array((string) $booking->status, ['confirmed', 'requested'], true)) {
            return response()->json(['errors' => [['message' => 'Confirm the request before setting date and time']]], 422);
        }

        $validator = Validator::make($request->all(), [
            'preferred_date' => 'required|date',
            'preferred_time' => 'required|string|max:32',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 422);
        }

        $timeRaw = (string) $request->preferred_time;
        $time = strlen($timeRaw) === 5 ? $timeRaw.':00' : $timeRaw;
        try {
            $when = \Carbon\Carbon::parse($request->preferred_date.' '.$time);
        } catch (\Throwable $e) {
            return response()->json(['errors' => [['message' => 'Invalid date or time']]], 422);
        }
        if ($when->lt(now()->subMinute())) {
            return response()->json(['errors' => [['message' => 'Choose a date and time from now onward']]], 422);
        }

        if ($booking->status === 'requested') {
            $booking->status = 'confirmed';
        }

        $alreadyNotified = (bool) $booking->schedule_notify_sent_at;
        $prevDate = $booking->preferred_date ? $booking->preferred_date->format('Y-m-d') : '';
        $prevTime = (string) ($booking->preferred_time ?? '');

        $booking->preferred_date = $request->preferred_date;
        $booking->preferred_time = $time;
        if (\Illuminate\Support\Facades\Schema::hasColumn('mentor_bookings', 'session_reminder_24h_sent_at')) {
            $booking->session_reminder_24h_sent_at = null;
        }
        $booking->save();

        $changed = $prevDate !== (string) $request->preferred_date || $prevTime !== $time;
        $notified = MentorBookingMailLogic::sendScheduleConfirmedNotify($booking, $alreadyNotified && $changed);

        return response()->json([
            'ok' => true,
            'message' => $notified
                ? 'Time saved. The other party was emailed (WhatsApp sent if configured).'
                : 'Time saved.',
            'booking' => MentorBookingLogic::formatBooking($booking->fresh(['service', 'mentee', 'mentor']), $forMentor),
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
        if (!$legacyProductId) {
            return response()->json([
                'errors' => [[
                    'message' => 'Online payment is not available for this mentor. Legacy product link is missing.',
                ]],
                'booking' => MentorBookingLogic::formatBooking($booking),
            ], 422);
        }
        $product = Product::find($legacyProductId);
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

    private static function paidBookingProductError(Mentor $mentor, MentorService $service): ?string
    {
        $amount = (float) $service->price;
        $taxAmount = MentorBookingLogic::calculateTaxAmount($mentor, $amount);
        if ($amount + $taxAmount <= 0) {
            return null;
        }

        if (!MentorLegacyProductLogic::ensureForMentor($mentor)) {
            return 'Online payment is not available for this mentor yet. Please contact support.';
        }

        return null;
    }
}
