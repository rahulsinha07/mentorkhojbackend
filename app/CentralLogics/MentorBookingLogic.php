<?php

namespace App\CentralLogics;

use App\Model\Mentor\Mentor;
use App\Model\Mentor\MentorBooking;
use App\Model\Mentor\MentorEarning;
use App\Model\Mentor\MentorService;
use App\Model\Order;
use App\Model\Product;

class MentorBookingLogic
{
    public static function createBooking(
        Mentor $mentor,
        MentorService $service,
        ?int $menteeUserId,
        array $data
    ): MentorBooking {
        $amount = (float) $service->price;
        $taxAmount = self::calculateTaxAmount($mentor, $amount);
        $feePercent = MentorLogic::platformFeePercent();
        $platformFee = round($amount * ($feePercent / 100), 2);
        $mentorNet = round($amount - $platformFee, 2);

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

    public static function calculateTaxAmount(Mentor $mentor, float $amount): float
    {
        if ($amount <= 0) {
            return 0;
        }

        $legacyProductId = $mentor->legacy_product_id ?? null;
        if ($legacyProductId) {
            $product = Product::find($legacyProductId);
            if ($product) {
                return round((float) Helpers::tax_calculate($product, $amount), 2);
            }
        }

        return 0;
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
        } elseif ($orderStatus === 'confirmed' || $orderStatus === 'processing') {
            if ($booking->status === 'requested' && $booking->payment_status === 'paid') {
                $booking->status = 'confirmed';
            }
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

    public static function formatBooking(MentorBooking $booking): array
    {
        $booking->loadMissing(['service', 'mentee', 'mentor']);
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
                'name' => trim(($booking->mentee->f_name ?? '') . ' ' . ($booking->mentee->l_name ?? '')),
            ] : null,
            'preferred_date' => $booking->preferred_date?->format('Y-m-d'),
            'preferred_time' => $booking->preferred_time,
            'mentee_note' => $booking->mentee_note,
            'status' => $booking->status,
            'amount' => $booking->amount,
            'tax_amount' => $booking->tax_amount,
            'total_amount' => round((float) $booking->amount + (float) $booking->tax_amount, 2),
            'payment_status' => $booking->payment_status,
            'requires_payment' => $booking->payment_status === 'pending',
            'can_retry_payment' => $booking->payment_status === 'failed',
            'created_at' => $booking->created_at?->toIso8601String(),
        ];
    }
}
