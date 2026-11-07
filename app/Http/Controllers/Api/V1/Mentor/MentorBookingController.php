<?php

namespace App\Http\Controllers\Api\V1\Mentor;

use App\CentralLogics\Helpers;
use App\CentralLogics\MentorBookingLogic;
use App\CentralLogics\MentorBookingMailLogic;
use App\CentralLogics\MentorFeedbackMailLogic;
use App\CentralLogics\UserNotificationLogic;
use App\CentralLogics\MentorLegacyProductLogic;
use App\CentralLogics\MentorLogic;
use App\CentralLogics\MentorSessionLifecycleLogic;
use App\CentralLogics\SessionCreditLogic;
use App\CentralLogics\SessionIcsLogic;
use App\Http\Controllers\Controller;
use App\Model\Branch;
use App\Model\Mentor\Mentor;
use App\Model\Mentor\MentorBooking;
use App\Model\Mentor\MentorService;
use App\Model\Product;
use App\Services\MeetingTranslationService;
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

        $user = $request->user();
        if ($user) {
            $isMentorAccount = ($user->account_type ?? '') === 'mentor';
            $ownsMentorProfile = Mentor::where('user_id', $user->id)->exists();
            $bookingOwnProfile = (int) ($mentor->user_id ?? 0) === (int) $user->id;
            if ($isMentorAccount || $ownsMentorProfile || $bookingOwnProfile) {
                return response()->json([
                    'errors' => [[
                        'message' => 'This is a mentor account. Please create a new student account and book from there.',
                    ]],
                ], 403);
            }
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

        $menteeId = $user?->id;

        if ($menteeId) {
            $existing = MentorBookingLogic::findRetryableBooking($menteeId, $mentor->id, $service->id);
            if ($existing) {
                if ($existing->payment_status === 'failed') {
                    $existing = MentorBookingLogic::prepareForPaymentRetry($existing);
                }

                // Upgrade legacy ₹0 free bookings to platform fee + GST.
                $existing = MentorBookingLogic::ensurePlatformFeeOnFreeBooking($existing, $mentor, $service);

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
        return $this->mySessions($request);
    }

    public function mySessions(Request $request): JsonResponse
    {
        $sessions = SessionCreditLogic::sessionsForMentee((int) $request->user()->id)->values();

        return response()->json([
            'sessions' => $sessions,
            'credits' => $sessions,
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

        $booking = MentorBooking::with(['service', 'mentor', 'events'])
            ->where('mentee_user_id', $request->user()->id)
            ->find($id);

        if (!$booking) {
            return response()->json(['errors' => [['message' => 'Booking not found']]], 404);
        }

        return response()->json([
            'booking' => MentorBookingLogic::formatBooking($booking),
        ]);
    }

    public function translateMyTranscript(Request $request, int $id, MeetingTranslationService $translator): JsonResponse
    {
        $booking = MentorBooking::where('mentee_user_id', $request->user()->id)->find($id);
        if (!$booking) {
            return response()->json(['errors' => [['message' => 'Booking not found']]], 404);
        }

        return $this->handleTranslateTranscript($request, $booking, $translator, false);
    }

    public function translateMentorTranscript(Request $request, int $id, MeetingTranslationService $translator): JsonResponse
    {
        $mentor = Mentor::where('user_id', $request->user()->id)->first();
        if (!$mentor) {
            return response()->json(['errors' => [['message' => 'Mentor profile not found']]], 404);
        }

        $booking = MentorBooking::where('mentor_id', $mentor->id)->find($id);
        if (!$booking) {
            return response()->json(['errors' => [['message' => 'Booking not found']]], 404);
        }

        return $this->handleTranslateTranscript($request, $booking, $translator, true);
    }

    private function handleTranslateTranscript(Request $request, MentorBooking $booking, MeetingTranslationService $translator, bool $forMentor): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'language' => 'required|string|in:'.implode(',', array_keys(MeetingTranslationService::SUPPORTED_LANGUAGES)),
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 422);
        }

        if (empty($booking->transcript_text)) {
            return response()->json(['errors' => [['message' => 'No transcript is available for this session yet.']]], 422);
        }

        if (!$translator->isConfigured()) {
            return response()->json(['errors' => [['message' => 'Transcript translation is not set up yet. Please contact support.']]], 422);
        }

        $translated = $translator->translateTranscript($booking, (string) $request->input('language'));
        if ($translated === null) {
            return response()->json(['errors' => [['message' => 'Could not translate the transcript right now. Please try again shortly.']]], 422);
        }

        return response()->json([
            'ok' => true,
            'booking' => MentorBookingLogic::formatBooking($booking->fresh(['service', 'mentee', 'mentor', 'events']), $forMentor),
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
        return $this->mentorSessions($request);
    }

    public function mentorSessions(Request $request): JsonResponse
    {
        $mentor = Mentor::where('user_id', $request->user()->id)->first();
        if (!$mentor) {
            return response()->json(['errors' => [['message' => 'Mentor profile not found']]], 404);
        }

        $sessions = SessionCreditLogic::sessionsForMentor((int) $mentor->id)->values();

        return response()->json([
            'sessions' => $sessions,
            'credits' => $sessions,
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

    public function scheduleMyBooking(Request $request, int $id): JsonResponse
    {
        $booking = MentorBooking::where('mentee_user_id', $request->user()->id)->find($id);
        if (!$booking) {
            return response()->json(['errors' => [['message' => 'Booking not found']]], 404);
        }

        return $this->handleSchedule($request, $booking, $request->user(), false);
    }

    public function requestReschedule(Request $request, int $id): JsonResponse
    {
        $booking = MentorBooking::where('mentee_user_id', $request->user()->id)->find($id);
        if (!$booking) {
            return response()->json(['errors' => [['message' => 'Booking not found']]], 404);
        }

        $validator = Validator::make($request->all(), [
            'preferred_date' => 'required|date',
            'preferred_time' => 'required|string|max:32',
            'reason' => 'nullable|string|max:2000',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 422);
        }

        try {
            $booking = MentorSessionLifecycleLogic::requestReschedule($booking, $request->user(), $request->all());
        } catch (\Throwable $e) {
            return response()->json(['errors' => [['message' => $e->getMessage()]]], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Reschedule request sent to your mentor.',
            'booking' => MentorBookingLogic::formatBooking($booking),
        ]);
    }

    public function scheduleMentorBooking(Request $request, int $id): JsonResponse
    {
        $mentor = Mentor::where('user_id', $request->user()->id)->first();
        if (!$mentor) {
            return response()->json(['errors' => [['message' => 'Mentor profile not found']]], 404);
        }

        $booking = MentorBooking::where('mentor_id', $mentor->id)->find($id);
        if (!$booking) {
            return response()->json(['errors' => [['message' => 'Booking not found']]], 404);
        }

        return $this->handleSchedule($request, $booking, $request->user(), true);
    }

    public function approveReschedule(Request $request, int $id): JsonResponse
    {
        $mentor = Mentor::where('user_id', $request->user()->id)->first();
        if (!$mentor) {
            return response()->json(['errors' => [['message' => 'Mentor profile not found']]], 404);
        }

        $booking = MentorBooking::where('mentor_id', $mentor->id)->find($id);
        if (!$booking) {
            return response()->json(['errors' => [['message' => 'Booking not found']]], 404);
        }

        try {
            $booking = MentorSessionLifecycleLogic::approveReschedule($booking, $request->user());
        } catch (\Throwable $e) {
            return response()->json(['errors' => [['message' => $e->getMessage()]]], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Reschedule approved.',
            'booking' => MentorBookingLogic::formatBooking($booking, true),
        ]);
    }

    public function rejectReschedule(Request $request, int $id): JsonResponse
    {
        $mentor = Mentor::where('user_id', $request->user()->id)->first();
        if (!$mentor) {
            return response()->json(['errors' => [['message' => 'Mentor profile not found']]], 404);
        }

        $booking = MentorBooking::where('mentor_id', $mentor->id)->find($id);
        if (!$booking) {
            return response()->json(['errors' => [['message' => 'Booking not found']]], 404);
        }

        $validated = $request->validate(['reason' => 'nullable|string|max:2000']);

        try {
            $booking = MentorSessionLifecycleLogic::rejectReschedule(
                $booking,
                $request->user(),
                $validated['reason'] ?? null
            );
        } catch (\Throwable $e) {
            return response()->json(['errors' => [['message' => $e->getMessage()]]], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Reschedule request rejected.',
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

        return $this->handleSchedule($request, $booking, $request->user(), true);
    }

    private function handleSchedule(Request $request, MentorBooking $booking, $actor, bool $forMentor): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'preferred_date' => 'required|date',
            'preferred_time' => 'required|string|max:32',
            'session_type' => 'nullable|string|max:32',
            'meeting_url' => 'nullable|string|max:512',
            'mentor_notes' => 'nullable|string|max:2000',
            'mentee_notes' => 'nullable|string|max:2000',
            'duration_minutes' => 'nullable|integer|min:5|max:480',
            'timezone' => 'nullable|string|max:64',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 422);
        }

        try {
            if (in_array((string) $booking->status, ['assigned', 'requested'], true)) {
                $booking = MentorSessionLifecycleLogic::scheduleSession($booking, $request->all(), $actor);
            } elseif ($forMentor) {
                $booking = MentorSessionLifecycleLogic::rescheduleDirect($booking, $actor, $request->all());
            } elseif ((string) $booking->status === 'confirmed') {
                $booking = MentorSessionLifecycleLogic::requestReschedule($booking, $actor, $request->all());
            } else {
                return response()->json(['errors' => [['message' => 'This session cannot be scheduled in its current state']]], 422);
            }
        } catch (\Throwable $e) {
            return response()->json(['errors' => [['message' => $e->getMessage()]]], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Session scheduled.',
            'booking' => MentorBookingLogic::formatBooking($booking->fresh(['service', 'mentee', 'mentor', 'events']), $forMentor),
        ]);
    }

    private function applyScheduleUpdate(Request $request, MentorBooking $booking, bool $forMentor): JsonResponse
    {
        return $this->handleSchedule($request, $booking, $request->user(), $forMentor);
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
        if ($mentor && $booking->service && (float) $booking->amount <= 0) {
            $booking = MentorBookingLogic::ensurePlatformFeeOnFreeBooking($booking, $mentor, $booking->service);
        }
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
        // Free sessions still charge ₹10 + GST platform fee — require online payment product.
        $payable = MentorBookingLogic::resolvePayableAmounts($mentor, $service);
        if ($payable['total'] <= 0) {
            return null;
        }

        if (!MentorLegacyProductLogic::ensureForMentor($mentor)) {
            return 'Online payment is not available for this mentor yet. Please contact support.';
        }

        return null;
    }


    public function showMentorBooking(Request $request, int $id): JsonResponse
    {
        $mentor = Mentor::where('user_id', $request->user()->id)->first();
        if (!$mentor) {
            return response()->json(['errors' => [['message' => 'Mentor profile not found']]], 404);
        }

        $booking = MentorBooking::with(['service', 'mentee', 'events'])
            ->where('mentor_id', $mentor->id)
            ->find($id);

        if (!$booking) {
            return response()->json(['errors' => [['message' => 'Booking not found']]], 404);
        }

        return response()->json([
            'booking' => MentorBookingLogic::formatBooking($booking, true),
        ]);
    }

    public function bookingHistory(Request $request, int $id): JsonResponse
    {
        $userId = (int) $request->user()->id;
        $booking = MentorBooking::with(['events'])->find($id);
        if (!$booking) {
            return response()->json(['errors' => [['message' => 'Booking not found']]], 404);
        }

        $mentor = Mentor::where('user_id', $userId)->first();
        $allowed = (int) $booking->mentee_user_id === $userId
            || ($mentor && (int) $booking->mentor_id === (int) $mentor->id);
        if (!$allowed) {
            return response()->json(['errors' => [['message' => 'Booking not found']]], 404);
        }

        return response()->json([
            'events' => $booking->events->map(fn ($e) => [
                'id' => $e->id,
                'event_type' => $e->event_type,
                'type' => $e->event_type,
                'actor_user_id' => $e->actor_user_id,
                'payload' => $e->payload,
                'created_at' => $e->created_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    public function cancelMyBooking(Request $request, int $id): JsonResponse
    {
        $booking = MentorBooking::where('mentee_user_id', $request->user()->id)->find($id);
        if (!$booking) {
            return response()->json(['errors' => [['message' => 'Booking not found']]], 404);
        }

        return $this->handleCancel($request, $booking, false);
    }

    public function cancelMentorBooking(Request $request, int $id): JsonResponse
    {
        $mentor = Mentor::where('user_id', $request->user()->id)->first();
        if (!$mentor) {
            return response()->json(['errors' => [['message' => 'Mentor profile not found']]], 404);
        }

        $booking = MentorBooking::where('mentor_id', $mentor->id)->find($id);
        if (!$booking) {
            return response()->json(['errors' => [['message' => 'Booking not found']]], 404);
        }

        return $this->handleCancel($request, $booking, true);
    }

    public function markNoShow(Request $request, int $id): JsonResponse
    {
        $mentor = Mentor::where('user_id', $request->user()->id)->first();
        if (!$mentor) {
            return response()->json(['errors' => [['message' => 'Mentor profile not found']]], 404);
        }

        $booking = MentorBooking::where('mentor_id', $mentor->id)->find($id);
        if (!$booking) {
            return response()->json(['errors' => [['message' => 'Booking not found']]], 404);
        }

        $validated = $request->validate(['reason' => 'nullable|string|max:2000']);

        try {
            $booking = MentorSessionLifecycleLogic::markNoShow(
                $booking,
                $request->user(),
                $validated['reason'] ?? null
            );
        } catch (\Throwable $e) {
            return response()->json(['errors' => [['message' => $e->getMessage()]]], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Session marked as no-show.',
            'booking' => MentorBookingLogic::formatBooking($booking, true),
        ]);
    }

    /**
     * Mentor requests feedback on a completed session. Sends the student a
     * MentorKhoj-branded email (CC'd to the MentorKhoj notify address, never
     * direct mentor-to-mentee email) plus an in-app notification. Only
     * allowed on the mentor's own completed bookings, with a 24h cooldown
     * between requests on the same booking to avoid spamming the student.
     */
    public function requestFeedback(Request $request, int $id): JsonResponse
    {
        $mentor = Mentor::where('user_id', $request->user()->id)->first();
        if (!$mentor) {
            return response()->json(['errors' => [['message' => 'Mentor profile not found']]], 404);
        }

        $booking = MentorBooking::where('mentor_id', $mentor->id)->find($id);
        if (!$booking) {
            return response()->json(['errors' => [['message' => 'Booking not found']]], 404);
        }

        if (!$booking->completed_at) {
            return response()->json(['errors' => [['message' => 'You can request feedback once this session is completed.']]], 422);
        }

        if (
            $booking->feedback_requested_at
            && $booking->feedback_requested_at->gt(now()->subHours(24))
        ) {
            return response()->json(['errors' => [['message' => 'Feedback was already requested recently for this session. Try again later.']]], 429);
        }

        $booking->feedback_requested_at = now();
        $booking->save();

        UserNotificationLogic::notifyFeedbackRequested($booking);
        MentorFeedbackMailLogic::sendFeedbackRequest($booking);

        return response()->json([
            'ok' => true,
            'message' => 'Feedback request sent.',
            'booking' => MentorBookingLogic::formatBooking($booking, true),
        ]);
    }

    public function downloadMyIcs(Request $request, int $id)
    {
        $booking = MentorBooking::where('mentee_user_id', $request->user()->id)->find($id);
        if (!$booking) {
            return response()->json(['errors' => [['message' => 'Booking not found']]], 404);
        }

        return $this->icsResponse($booking);
    }

    public function downloadMentorIcs(Request $request, int $id)
    {
        $mentor = Mentor::where('user_id', $request->user()->id)->first();
        if (!$mentor) {
            return response()->json(['errors' => [['message' => 'Mentor profile not found']]], 404);
        }

        $booking = MentorBooking::where('mentor_id', $mentor->id)->find($id);
        if (!$booking) {
            return response()->json(['errors' => [['message' => 'Booking not found']]], 404);
        }

        return $this->icsResponse($booking);
    }

    private function handleCancel(Request $request, MentorBooking $booking, bool $forMentor): JsonResponse
    {
        $validated = $request->validate(['reason' => 'required|string|max:2000']);

        try {
            $booking = MentorSessionLifecycleLogic::cancelSession(
                $booking,
                $request->user(),
                $validated['reason']
            );
        } catch (\Throwable $e) {
            return response()->json(['errors' => [['message' => $e->getMessage()]]], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => SessionCreditLogic::isSessionPackBooking($booking) && $booking->status === 'assigned'
                ? 'Session released back to pending schedule.'
                : 'Session cancelled.',
            'booking' => MentorBookingLogic::formatBooking($booking->fresh(['service', 'mentee', 'mentor', 'events']), $forMentor),
        ]);
    }

    private function icsResponse(MentorBooking $booking)
    {
        try {
            $ics = SessionIcsLogic::generate($booking);
        } catch (\Throwable $e) {
            return response()->json(['errors' => [['message' => $e->getMessage()]]], 422);
        }

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.SessionIcsLogic::filename($booking).'"',
        ]);
    }

}
