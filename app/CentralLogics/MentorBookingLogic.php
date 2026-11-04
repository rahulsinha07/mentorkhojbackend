<?php

namespace App\CentralLogics;

use App\Model\Mentor\Mentor;
use App\Model\Mentor\MentorBooking;
use App\Model\Mentor\MentorEarning;
use App\Model\Mentor\MentorService;
use App\Model\Order;

class MentorBookingLogic
{
    /** Free mentor sessions charge a Mentorkhoj platform fee of ₹10 + GST (not mentor session fee). */
    public const FREE_SESSION_PLATFORM_FEE_INR = 10.0;

    /**
     * Resolve charge amounts for a mentor service.
     * Free services (price <= 0) → amount 10 + GST; mentor_net 0 (fee is platform-only).
     *
     * @return array{amount: float, tax_amount: float, platform_fee: float, mentor_net: float, total: float, is_free_session: bool}
     */
    public static function resolvePayableAmounts(Mentor $mentor, MentorService $service): array
    {
        return self::resolvePayableAmountsForPrice($mentor, (float) $service->price);
    }

    /**
     * Resolve charge amounts for any priced item (session or document bundle).
     * Free items (price <= 0) → amount 10 + GST; mentor_net 0 (fee is platform-only).
     *
     * @return array{amount: float, tax_amount: float, platform_fee: float, mentor_net: float, total: float, is_free_session: bool}
     */
    public static function resolvePayableAmountsForPrice(Mentor $mentor, float $price): array
    {
        $servicePrice = $price;

        if ($servicePrice <= 0) {
            $amount = self::FREE_SESSION_PLATFORM_FEE_INR;
            $taxAmount = self::calculateTaxAmount($mentor, $amount);
            // Entire base fee is platform revenue; GST is collected on top.
            $platformFee = $amount;
            $mentorNet = 0.0;

            return [
                'amount' => $amount,
                'tax_amount' => $taxAmount,
                'platform_fee' => $platformFee,
                'mentor_net' => $mentorNet,
                'total' => round($amount + $taxAmount, 2),
                'is_free_session' => true,
            ];
        }

        $amount = $servicePrice;
        $taxAmount = self::calculateTaxAmount($mentor, $amount);
        $feePercent = MentorLogic::platformFeePercent();
        $platformFee = round($amount * ($feePercent / 100), 2);
        $mentorNet = round($amount - $platformFee, 2);

        return [
            'amount' => $amount,
            'tax_amount' => $taxAmount,
            'platform_fee' => $platformFee,
            'mentor_net' => $mentorNet,
            'total' => round($amount + $taxAmount, 2),
            'is_free_session' => false,
        ];
    }

    /**
     * Upgrade a legacy ₹0 free booking to platform fee + GST so checkout/Razorpay work.
     */
    public static function ensurePlatformFeeOnFreeBooking(MentorBooking $booking, Mentor $mentor, MentorService $service): MentorBooking
    {
        if ((float) $booking->amount > 0) {
            return $booking;
        }

        $payable = self::resolvePayableAmounts($mentor, $service);
        if ($payable['total'] <= 0) {
            return $booking;
        }

        $booking->update([
            'amount' => $payable['amount'],
            'tax_amount' => $payable['tax_amount'],
            'platform_fee' => $payable['platform_fee'],
            'mentor_net' => $payable['mentor_net'],
            'payment_status' => 'pending',
            'status' => $booking->status === 'cancelled' ? 'requested' : $booking->status,
        ]);

        return $booking->fresh();
    }

    public static function createBooking(
        Mentor $mentor,
        MentorService $service,
        ?int $menteeUserId,
        array $data
    ): MentorBooking {
        $payable = self::resolvePayableAmounts($mentor, $service);
        $amount = $payable['amount'];
        $taxAmount = $payable['tax_amount'];
        $platformFee = $payable['platform_fee'];
        $mentorNet = $payable['mentor_net'];

        $booking = MentorBooking::create([
            'mentor_id' => $mentor->id,
            'mentor_service_id' => $service->id,
            'mentee_user_id' => $menteeUserId,
            'preferred_date' => $data['preferred_date'] ?? null,
            'preferred_time' => $data['preferred_time'] ?? null,
            'mentee_note' => $data['mentee_note'] ?? null,
            'status' => 'requested',
            'amount' => $amount,
            'tax_amount' => $taxAmount,
            'platform_fee' => $platformFee,
            'mentor_net' => $mentorNet,
            'payment_status' => ($amount + $taxAmount) <= 0 ? 'paid' : 'pending',
        ]);

        if ($booking->payment_status === 'paid') {
            MentorEarningsLogic::creditBooking($booking);
        }

        MentorBookingMailLogic::maybeSendAfterPayment($booking);

        return $booking;
    }

    public static function markPaymentFailed(MentorBooking $booking, ?string $reason = null): MentorBooking
    {
        if ($booking->payment_status === 'paid') {
            return $booking;
        }

        $booking->update([
            'payment_status' => 'failed',
            'status' => 'cancelled',
        ]);

        if ($reason) {
            \Illuminate\Support\Facades\Log::info('Mentor booking payment failed', [
                'booking_id' => $booking->id,
                'reason' => $reason,
            ]);
        }

        return $booking->fresh();
    }

    public static function prepareForPaymentRetry(MentorBooking $booking): MentorBooking
    {
        if ($booking->payment_status === 'paid') {
            throw new \InvalidArgumentException('This booking is already paid.');
        }

        if (!in_array($booking->payment_status, ['pending', 'failed'], true)) {
            throw new \InvalidArgumentException('Payment retry is not available for this booking.');
        }

        $booking->update([
            'payment_status' => 'pending',
            'status' => 'requested',
            'legacy_order_id' => null,
        ]);

        return $booking->fresh();
    }

    public static function findRetryableBooking(
        int $menteeUserId,
        int $mentorId,
        int $serviceId
    ): ?MentorBooking {
        return MentorBooking::where('mentee_user_id', $menteeUserId)
            ->where('mentor_id', $mentorId)
            ->where('mentor_service_id', $serviceId)
            ->whereIn('payment_status', ['pending', 'failed'])
            ->whereIn('status', ['requested', 'cancelled'])
            ->latest('id')
            ->first();
    }

    public static function gstPercent(): float
    {
        $pct = (float) config('mentor.gst_percent', 18);

        return $pct > 0 ? $pct : 0.0;
    }

    public static function calculateTaxAmount(Mentor $mentor, float $amount): float
    {
        if ($amount <= 0) {
            return 0;
        }

        // Student paid sessions: flat GST on service price (independent of legacy product tax).
        $pct = self::gstPercent();
        if ($pct <= 0) {
            return 0;
        }

        return round($amount * ($pct / 100), 2);
    }

    /**
     * Link a mentor booking to a legacy order and sync payment / status.
     */
    public static function syncFromLegacyOrder(MentorBooking $booking, Order $order): void
    {
        $previousStatus = $booking->status;
        $booking->legacy_order_id = $order->id;

        $orderPayment = (string) ($order->payment_status ?? 'unpaid');
        if (in_array($orderPayment, ['paid', 'partially_paid'], true)) {
            $booking->payment_status = 'paid';
            if (!$booking->earnings()->exists()) {
                MentorEarningsLogic::creditBooking($booking);
            }
        } elseif (in_array($orderPayment, ['unpaid', 'failed'], true)) {
            if ($booking->payment_status !== 'paid') {
                $booking->payment_status = $orderPayment === 'failed' ? 'failed' : 'pending';
            }
        }

        $orderStatus = (string) ($order->order_status ?? 'pending');
        if ($orderStatus === 'delivered' || $orderStatus === 'completed') {
            $booking->status = 'completed';
        } elseif (in_array($orderStatus, ['cancelled', 'canceled', 'failed', 'returned'], true)) {
            $booking->status = 'cancelled';
            if ($booking->payment_status !== 'paid') {
                $booking->payment_status = 'failed';
            }
        }

        $booking->save();

        $booking = $booking->fresh();
        MentorBookingMailLogic::maybeSendAfterPayment($booking);

        if ($previousStatus !== 'confirmed' && $booking->status === 'confirmed') {
            MentorBookingMailLogic::sendMenteeConfirmedEmail($booking);
        }
    }

    public static function markPaid(MentorBooking $booking, ?Order $order = null): MentorBooking
    {
        if ($booking->payment_status === 'paid') {
            MentorBookingMailLogic::maybeSendAfterPayment($booking->fresh());

            return $booking->fresh();
        }

        if ($order) {
            self::syncFromLegacyOrder($booking, $order);

            return $booking->fresh();
        }

        $booking->update([
            'payment_status' => 'paid',
            'status' => $booking->status === 'cancelled' ? 'requested' : $booking->status,
        ]);

        if (!$booking->earnings()->exists()) {
            MentorEarningsLogic::creditBooking($booking);
        }

        $booking = $booking->fresh();
        MentorBookingMailLogic::maybeSendAfterPayment($booking);

        return $booking;
    }

    /**
     * Verify a Razorpay payment and mark the mentor booking as paid.
     *
     * @param  array<string, mixed>  $paymentData
     */
    public static function verifyAndMarkPaid(MentorBooking $booking, array $paymentData): MentorBooking
    {
        if ($booking->payment_status === 'paid') {
            return self::markPaid($booking);
        }

        if (!empty($booking->legacy_order_id)) {
            $order = Order::find($booking->legacy_order_id);
            if ($order && in_array((string) $order->payment_status, ['paid', 'partially_paid'], true)) {
                return self::markPaid($booking, $order);
            }
        }

        $transactionReference = trim((string) (
            $paymentData['transaction_reference']
            ?? $paymentData['razorpay_payment_id']
            ?? ''
        ));

        if ($transactionReference !== '') {
            $linkedOrder = Order::where('transaction_reference', $transactionReference)
                ->where('user_id', $booking->mentee_user_id)
                ->latest('id')
                ->first();

            if ($linkedOrder && in_array((string) $linkedOrder->payment_status, ['paid', 'partially_paid'], true)) {
                return self::markPaid($booking, $linkedOrder);
            }

            self::assertRazorpayPaymentMatchesBooking($booking, $transactionReference);

            if (!empty($booking->legacy_order_id)) {
                Order::where('id', $booking->legacy_order_id)->update([
                    'transaction_reference' => $transactionReference,
                    'payment_status' => 'paid',
                    'order_status' => 'confirmed',
                ]);
            }

            return self::markPaid($booking);
        }

        if (!empty($paymentData['razorpay_order_id'])
            && !empty($paymentData['razorpay_payment_id'])
            && !empty($paymentData['razorpay_signature'])) {
            self::assertRazorpaySignature(
                (string) $paymentData['razorpay_order_id'],
                (string) $paymentData['razorpay_payment_id'],
                (string) $paymentData['razorpay_signature'],
            );

            if (!empty($booking->legacy_order_id)) {
                Order::where('id', $booking->legacy_order_id)->update([
                    'transaction_reference' => (string) $paymentData['razorpay_payment_id'],
                    'payment_status' => 'paid',
                    'order_status' => 'confirmed',
                ]);
            }

            return self::markPaid($booking);
        }

        throw new \RuntimeException('Payment details are missing or invalid.');
    }

    public static function syncPendingPaidBookingsForUser(int $userId): int
    {
        $synced = 0;

        $bookings = MentorBooking::where('mentee_user_id', $userId)
            ->where('payment_status', 'pending')
            ->whereNotNull('legacy_order_id')
            ->get();

        foreach ($bookings as $booking) {
            $order = Order::find($booking->legacy_order_id);
            if ($order && in_array((string) $order->payment_status, ['paid', 'partially_paid'], true)) {
                self::syncFromLegacyOrder($booking, $order);
                $synced++;
            }
        }

        return $synced;
    }

    public static function resendMissingBookingEmails(?int $bookingId = null): array
    {
        $query = MentorBooking::query()
            ->where('payment_status', 'paid')
            ->with(['mentor.user', 'service', 'mentee']);

        if ($bookingId) {
            $query->where('id', $bookingId);
        }

        $menteeSent = 0;
        $mentorSent = 0;

        foreach ($query->get() as $booking) {
            $hadMenteeEmail = (bool) ($booking->mentee_booked_email_sent_at ?? false);
            $hadMentorEmail = (bool) ($booking->mentor_notify_email_sent_at ?? false);

            MentorBookingMailLogic::sendBookingPlacedEmails($booking);
            $booking = $booking->fresh();

            if (!$hadMenteeEmail && ($booking->mentee_booked_email_sent_at ?? false)) {
                $menteeSent++;
            }
            if (!$hadMentorEmail && ($booking->mentor_notify_email_sent_at ?? false)) {
                $mentorSent++;
            }
        }

        return [
            'mentee_emails_sent' => $menteeSent,
            'mentor_emails_sent' => $mentorSent,
        ];
    }

    private static function assertRazorpayPaymentMatchesBooking(MentorBooking $booking, string $transactionReference): void
    {
        $creds = app(\App\Services\RazorpaySeminarService::class)->credentials();
        if (!$creds['key_id'] || !$creds['key_secret']) {
            throw new \RuntimeException('RazorPay is not configured.');
        }

        $response = \Illuminate\Support\Facades\Http::withBasicAuth($creds['key_id'], $creds['key_secret'])
            ->get('https://api.razorpay.com/v1/payments/' . $transactionReference);

        if (!$response->successful()) {
            throw new \RuntimeException('Could not verify payment.');
        }

        $payment = $response->json();
        $status = (string) ($payment['status'] ?? '');
        if (!in_array($status, ['captured', 'authorized'], true)) {
            throw new \RuntimeException('Payment was not completed.');
        }

        $expectedPaise = (int) round(((float) $booking->amount + (float) $booking->tax_amount) * 100);
        $paidPaise = (int) ($payment['amount'] ?? 0);
        if ($paidPaise > 0 && $expectedPaise > 0 && $paidPaise !== $expectedPaise) {
            throw new \RuntimeException('Payment amount does not match booking fee.');
        }
    }

    private static function assertRazorpaySignature(string $orderId, string $paymentId, string $signature): void
    {
        $secret = app(\App\Services\RazorpaySeminarService::class)->credentials()['key_secret'];
        if (!$secret) {
            throw new \RuntimeException('RazorPay is not configured.');
        }

        $expected = hash_hmac('sha256', $orderId . '|' . $paymentId, $secret);
        if (!hash_equals($expected, $signature)) {
            throw new \RuntimeException('Invalid payment signature.');
        }
    }

    /**
     * @param  int[]  $bookingIds
     * @param  Order  $order
     */
    public static function syncBookingsFromLegacyOrder(array $bookingIds, Order $order, ?int $userId = null): void
    {
        foreach ($bookingIds as $bookingId) {
            $booking = MentorBooking::find((int) $bookingId);
            if (!$booking) {
                continue;
            }
            if ($userId !== null && (int) $booking->mentee_user_id !== (int) $userId) {
                continue;
            }
            self::syncFromLegacyOrder($booking, $order);
        }
    }

    public static function syncBookingsForOrder(Order $order): void
    {
        $bookings = MentorBooking::where('legacy_order_id', $order->id)->get();
        foreach ($bookings as $booking) {
            self::syncFromLegacyOrder($booking, $order);
        }
    }

    public static function formatBooking(MentorBooking $booking, bool $forMentor = false): array
    {
        $booking->loadMissing(['service', 'mentee', 'mentor', 'events']);
        $bookingSource = SessionCreditLogic::normalizeBookingSourceForApi($booking->booking_source ?? 'paid');
        $when = SessionCreditLogic::sessionDateTime($booking);

        return [
            'id' => $booking->id,
            'mentor_id' => $booking->mentor_id,
            'legacy_order_id' => $booking->legacy_order_id,
            'mentor' => $booking->mentor ? [
                'id' => $booking->mentor->id,
                'username' => $booking->mentor->username,
                'display_name' => $booking->mentor->display_name,
                'headline' => $booking->mentor->headline,
                'legacy_product_id' => $booking->mentor->legacy_product_id,
            ] : null,
            'service' => $booking->service ? MentorLogic::formatService($booking->service) : null,
            'mentee' => $booking->mentee ? [
                'id' => $booking->mentee->id,
                'name' => $forMentor
                    ? SessionChatLogic::firstName($booking->mentee->f_name ?? '')
                    : trim(($booking->mentee->f_name ?? '') . ' ' . ($booking->mentee->l_name ?? '')),
                'first_name' => SessionChatLogic::firstName($booking->mentee->f_name ?? ''),
            ] : null,
            'preferred_date' => $booking->preferred_date?->format('Y-m-d'),
            'preferred_time' => $booking->preferred_time,
            'session_number' => $booking->session_number,
            'total_sessions' => $booking->total_sessions,
            'duration_minutes' => $booking->duration_minutes ?? $booking->service?->duration_minutes,
            'session_type' => $booking->session_type ?? $booking->service?->meeting_type,
            'timezone' => $booking->timezone ?? MentorSessionLifecycleLogic::appTimezone(),
            'meeting_url' => $booking->meeting_url,
            'meeting_link_source' => $booking->meeting_link_source,
            'scheduled_at' => $booking->scheduled_at?->toIso8601String(),
            'starts_at_iso' => $when?->toIso8601String(),
            'mentee_note' => $booking->mentee_note,
            'status' => $booking->status,
            'status_label' => MentorSessionLifecycleLogic::statusLabel((string) $booking->status),
            'amount' => $booking->amount,
            'tax_amount' => $booking->tax_amount,
            'platform_fee' => $booking->platform_fee,
            'mentor_net' => $booking->mentor_net,
            'total_amount' => round((float) $booking->amount + (float) $booking->tax_amount, 2),
            'payment_status' => $booking->payment_status,
            'booking_source' => $bookingSource,
            'session_pack_id' => $booking->session_credit_id,
            'session_credit_id' => $booking->session_credit_id,
            'requires_payment' => $booking->payment_status === 'pending' && !SessionCreditLogic::isSessionPackBooking($booking),
            'can_retry_payment' => $booking->payment_status === 'failed',
            'can_schedule' => SessionCreditLogic::canSchedule($booking),
            'can_reschedule' => SessionCreditLogic::canReschedule($booking),
            'can_request_reschedule' => SessionCreditLogic::canRequestReschedule($booking),
            'can_approve_reschedule' => SessionCreditLogic::canApproveReschedule($booking),
            'can_mark_complete' => SessionCreditLogic::canMarkComplete($booking),
            'can_cancel' => SessionCreditLogic::canCancel($booking),
            'can_mark_no_show' => SessionCreditLogic::canMarkNoShow($booking),
            'feedback_requested_at' => $booking->feedback_requested_at?->toIso8601String(),
            'can_request_feedback' => (bool) $booking->completed_at
                && (!$booking->feedback_requested_at || $booking->feedback_requested_at->lte(now()->subHours(24))),
            'can_join_session' => SessionCreditLogic::canJoinSession($booking),
            'join_window_minutes' => 15,
            'is_upcoming' => SessionCreditLogic::isUpcoming($booking),
            'is_past' => SessionCreditLogic::isPast($booking),
            'events' => $booking->events->map(fn ($e) => [
                'id' => $e->id,
                'event_type' => $e->event_type,
                'type' => $e->event_type,
                'actor_user_id' => $e->actor_user_id,
                'payload' => $e->payload,
                'created_at' => $e->created_at?->toIso8601String(),
            ])->values(),
            'emails_sent' => [
                'mentee_booked' => (bool) ($booking->mentee_booked_email_sent_at ?? false),
                'mentor_notify' => (bool) ($booking->mentor_notify_email_sent_at ?? false),
                'mentee_confirmed' => (bool) ($booking->mentee_confirmed_email_sent_at ?? false),
                'schedule_notify' => (bool) ($booking->schedule_notify_sent_at ?? false),
                'session_reminder_24h' => (bool) ($booking->session_reminder_24h_sent_at ?? false),
                'session_reminder_1h' => (bool) ($booking->session_reminder_1h_sent_at ?? false),
            ],
            'created_at' => $booking->created_at?->toIso8601String(),
        ];
    }
}
