<?php

namespace App\CentralLogics;

use App\Model\Mentor\Mentor;
use App\Model\Mentor\MentorBooking;
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

    public static function summary(Mentor $mentor): array
    {
        $gross = (float) MentorEarning::where('mentor_id', $mentor->id)
            ->whereIn('type', ['booking'])
            ->sum('gross');
        $fees = (float) MentorEarning::where('mentor_id', $mentor->id)
            ->whereIn('type', ['booking'])
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
        ];
    }
}
