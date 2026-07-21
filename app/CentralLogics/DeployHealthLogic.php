<?php

namespace App\CentralLogics;

use App\Services\RazorpaySeminarService;
use Illuminate\Support\Facades\Schema;

class DeployHealthLogic
{
    /** @return array<string, bool> */
    public static function checks(): array
    {
        $razorpay = app(RazorpaySeminarService::class)->credentials();

        return [
            'seminar_bookings_table' => Schema::hasTable('seminar_bookings'),
            'seminars_fee_amount_column' => Schema::hasColumn('seminars', 'fee_amount'),
            'seminars_currency_column' => Schema::hasColumn('seminars', 'currency'),
            'mentor_welcome_email_column' => Schema::hasColumn('mentors', 'welcome_email_sent_at'),
            'mentor_booking_email_columns' => Schema::hasColumn('mentor_bookings', 'mentee_booked_email_sent_at'),
            'mentor_welcome_mail_logic' => class_exists(MentorWelcomeMailLogic::class),
            'mentor_booking_mail_logic' => class_exists(MentorBookingMailLogic::class),
            'razorpay_configured' => !empty($razorpay['key_id']) && !empty($razorpay['key_secret']),
        ];
    }

    public static function ok(): bool
    {
        $checks = self::checks();
        $required = [
            'seminar_bookings_table',
            'seminars_fee_amount_column',
            'seminars_currency_column',
            'mentor_welcome_email_column',
            'mentor_booking_email_columns',
            'mentor_welcome_mail_logic',
            'mentor_booking_mail_logic',
        ];

        foreach ($required as $key) {
            if (empty($checks[$key])) {
                return false;
            }
        }

        return true;
    }
}
