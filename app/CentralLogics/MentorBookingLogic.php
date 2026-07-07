<?php

namespace App\CentralLogics;

use App\Model\Mentor\Mentor;
use App\Model\Mentor\MentorBooking;
use App\Model\Mentor\MentorEarning;
use App\Model\Mentor\MentorService;

class MentorBookingLogic
{
    public static function createBooking(
        Mentor $mentor,
        MentorService $service,
        ?int $menteeUserId,
        array $data
    ): MentorBooking {
        $amount = (float) $service->price;
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
            'tax_amount' => 0,
            'platform_fee' => $platformFee,
            'mentor_net' => $mentorNet,
            'payment_status' => $amount <= 0 ? 'paid' : 'pending',
        ]);

        if ($booking->payment_status === 'paid') {
            MentorEarningsLogic::creditBooking($booking);
        }

        return $booking;
    }

    public static function formatBooking(MentorBooking $booking): array
    {
        $booking->loadMissing(['service', 'mentee', 'mentor']);
        return [
            'id' => $booking->id,
            'mentor_id' => $booking->mentor_id,
            'mentor' => $booking->mentor ? [
                'id' => $booking->mentor->id,
                'username' => $booking->mentor->username,
                'display_name' => $booking->mentor->display_name,
                'headline' => $booking->mentor->headline,
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
            'payment_status' => $booking->payment_status,
            'created_at' => $booking->created_at?->toIso8601String(),
        ];
    }
}
