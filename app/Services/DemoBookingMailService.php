<?php

namespace App\Services;

use App\Mail\DemoBooked;
use App\Mail\DemoBookedAdminAlert;
use App\Mail\DemoMentorAssigned;
use App\CentralLogics\DemoBookingClaimLogic;
use App\Model\DemoBooking;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

/**
 * Demo booking emails — same stack as seminar booking.
 *
 * Uses Laravel Mail facade + Hostinger SMTP already configured for
 * SeminarBookingConfirmed. Do not add separate mail credentials on Next.js.
 *
 * @see \App\Services\RazorpaySeminarService::sendConfirmationEmail
 */
class DemoBookingMailService
{
    /**
     * @return array{email_student: string, email_admin: string}
     */
    public function sendConfirmationEmails(DemoBooking $booking): array
    {
        $result = [
            'email_student' => 'skipped',
            'email_admin' => 'skipped',
        ];

        if ($booking->email_sent_at) {
            $result['email_student'] = 'already_sent';
            $result['email_admin'] = 'already_sent';

            return $result;
        }

        if (!empty($booking->email)) {
            try {
                Mail::to($booking->email)->send(new DemoBooked($booking));
                $result['email_student'] = 'success';
            } catch (\Throwable $e) {
                $result['email_student'] = 'error';
                Log::error('Demo booking student email failed', [
                    'booking_id' => $booking->id,
                    'error' => $e->getMessage(),
                ]);
            }
        } else {
            $result['email_student'] = 'no_email';
        }

        $admin = env('DEMO_BOOKING_ADMIN_EMAIL', env('MAIL_FROM_ADDRESS', 'admin@mentorkhoj.com'));
        $ops = [env('MENTORKHOJ_NOTIFY_EMAIL', 'mentorkhoj@gmail.com')];
        if ($admin && !in_array(strtolower((string) $admin), array_map('strtolower', $ops), true)) {
            array_unshift($ops, $admin);
        }
        $ops = array_values(array_filter(array_unique($ops)));

        try {
            Mail::to($ops)->send(new DemoBookedAdminAlert($booking));
            $result['email_admin'] = 'success';
        } catch (\Throwable $e) {
            $result['email_admin'] = 'error';
            Log::error('Demo booking admin email failed', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
        }

        if ($result['email_admin'] === 'success' || $result['email_student'] === 'success') {
            if (Schema::hasColumn('demo_bookings', 'email_sent_at')) {
                $booking->update(['email_sent_at' => now()]);
            }
        }

        return $result;
    }

    /**
     * @return string success|no_email|error
     */
    public function sendMentorAssignedEmail(DemoBooking $booking): string
    {
        $email = trim((string) $booking->email);
        if ($email === '') {
            return 'no_email';
        }

        try {
            $hasAccount = DemoBookingClaimLogic::studentHasAccount($booking);
            $cta = DemoBookingClaimLogic::profileCtaUrl($booking);
            Mail::to($email)->send(new DemoMentorAssigned($booking, $cta, $hasAccount));

            return 'success';
        } catch (\Throwable $e) {
            Log::error('Demo mentor assigned email failed', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);

            return 'error';
        }
    }
}
