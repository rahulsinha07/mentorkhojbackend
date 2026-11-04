<?php

namespace App\CentralLogics;

use App\Model\Mentor\Mentor;
use App\Model\Mentor\MentorBooking;
use App\Model\Mentor\MentorDocumentPurchase;
use App\Model\Mentor\MentorEarning;
use App\Model\Mentor\MentorPayout;

class MentorEarningsLogic
{
    public static function creditBooking(MentorBooking $booking): MentorEarning
    {
        return MentorEarning::create([
            'mentor_id' => $booking->mentor_id,
            'mentor_booking_id' => $booking->id,
            'type' => 'booking',
            'gross' => $booking->amount,
            'fee' => $booking->platform_fee,
            'net' => $booking->mentor_net,
            'status' => 'completed',
        ]);
    }

    public static function creditDocumentPurchase(MentorDocumentPurchase $purchase): MentorEarning
    {
        return MentorEarning::create([
            'mentor_id' => $purchase->mentor_id,
            'mentor_document_purchase_id' => $purchase->id,
            'type' => 'document',
            'gross' => $purchase->amount,
            'fee' => $purchase->platform_fee,
            'net' => $purchase->mentor_net,
            'status' => 'completed',
        ]);
    }

    public static function summary(Mentor $mentor): array
    {
        $gross = (float) MentorEarning::where('mentor_id', $mentor->id)
            ->whereIn('type', ['booking', 'document'])
            ->sum('gross');
        $fees = (float) MentorEarning::where('mentor_id', $mentor->id)
            ->whereIn('type', ['booking', 'document'])
            ->sum('fee');
        $net = (float) MentorEarning::where('mentor_id', $mentor->id)
            ->where('status', 'completed')
            ->sum('net');
        $paidOut = (float) MentorPayout::where('mentor_id', $mentor->id)
            ->where('status', 'approved')
            ->sum('amount');
        $pendingPayout = (float) MentorPayout::where('mentor_id', $mentor->id)
            ->where('status', 'pending')
            ->sum('amount');

        return [
            'gross' => round($gross, 2),
            'platform_fees' => round($fees, 2),
            'net' => round($net, 2),
            'paid_out' => round($paidOut, 2),
            'available_balance' => round(max(0, $net - $paidOut - $pendingPayout), 2),
            'pending_payout' => round($pendingPayout, 2),
            'platform_fee_percent' => MentorLogic::platformFeePercent(),
        ];
    }

    /** @return array<string, mixed> */
    public static function formatTransaction(MentorEarning $earning): array
    {
        $earning->loadMissing(['booking.mentee', 'booking.service']);

        $booking = $earning->booking;
        $menteeName = $booking?->mentee
            ? trim(($booking->mentee->f_name ?? '').' '.($booking->mentee->l_name ?? ''))
            : null;
        $bookingSource = SessionCreditLogic::normalizeBookingSourceForApi($booking?->booking_source ?? 'paid');
        $sessionDate = null;
        if ($booking?->preferred_date) {
            $date = $booking->preferred_date instanceof \Carbon\Carbon
                ? $booking->preferred_date->format('Y-m-d')
                : (string) $booking->preferred_date;
            $time = $booking->preferred_time ? substr((string) $booking->preferred_time, 0, 5) : null;
            $sessionDate = $time ? "{$date} {$time}" : $date;
        }

        $label = $menteeName ? "Session completed — {$menteeName}" : 'Session completed';

        return [
            'id' => $earning->id,
            'type' => $earning->type,
            'gross' => round((float) $earning->gross, 2),
            'fee' => round((float) $earning->fee, 2),
            'net' => round((float) $earning->net, 2),
            'status' => $earning->status,
            'booking_source' => $bookingSource,
            'mentee_name' => $menteeName,
            'session_date' => $sessionDate,
            'description' => $label,
            'platform_fee_percent' => MentorLogic::platformFeePercent(),
            'created_at' => $earning->created_at?->toIso8601String(),
        ];
    }
}
