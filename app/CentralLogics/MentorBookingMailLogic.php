<?php

namespace App\CentralLogics;

use App\Mail\Form\FormSubmissionMail;
use App\Model\Mentor\Mentor;
use App\Model\Mentor\MentorBooking;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class MentorBookingMailLogic
{
    public static function sendBookingPlacedEmails(MentorBooking $booking): bool
    {
        if (!FormMailLogic::isMailEnabled()) {
            Log::warning('Mentor booking mail skipped: SMTP not configured');

            return false;
        }

        $booking->loadMissing(['mentor.user', 'service', 'mentee']);
        $menteeSent = false;

        if ((!self::hasEmailTimestampColumn('mentee_booked_email_sent_at') || !$booking->mentee_booked_email_sent_at) && self::menteeEmail($booking)) {
            try {
                Mail::to(self::menteeEmail($booking))->send(new FormSubmissionMail(
                    'Your MentorKhoj session is booked | MentorKhoj',
                    'email-templates.form.mentor-booking-mentee-booked',
                    FormMailLogic::withBrandPublic(self::bookingContext($booking))
                ));
                if (self::hasEmailTimestampColumn('mentee_booked_email_sent_at')) {
                    $booking->mentee_booked_email_sent_at = now();
                }
                $menteeSent = true;
            } catch (\Throwable $e) {
                Log::warning('Mentor booking mentee email failed: ' . $e->getMessage());
            }
        }

        if ((!self::hasEmailTimestampColumn('mentor_notify_email_sent_at') || !$booking->mentor_notify_email_sent_at) && self::mentorEmail($booking)) {
            try {
                Mail::to(self::mentorEmail($booking))->send(new FormSubmissionMail(
                    'New session booking — please confirm | MentorKhoj',
                    'email-templates.form.mentor-booking-mentor-confirm',
                    FormMailLogic::withBrandPublic(self::bookingContext($booking, forMentor: true))
                ));
                if (self::hasEmailTimestampColumn('mentor_notify_email_sent_at')) {
                    $booking->mentor_notify_email_sent_at = now();
                }
            } catch (\Throwable $e) {
                Log::warning('Mentor booking mentor email failed: ' . $e->getMessage());
            }
        }

        if ($booking->isDirty()) {
            $booking->save();
        }

        return $menteeSent;
    }

    public static function sendMenteeConfirmedEmail(MentorBooking $booking): bool
    {
        if (!FormMailLogic::isMailEnabled()) {
            return false;
        }

        $booking->loadMissing(['mentor.user', 'service', 'mentee']);

        if ((self::hasEmailTimestampColumn('mentee_confirmed_email_sent_at') && $booking->mentee_confirmed_email_sent_at)
            || !self::menteeEmail($booking)) {
            return false;
        }

        try {
            Mail::to(self::menteeEmail($booking))->send(new FormSubmissionMail(
                'Session confirmed by your mentor | MentorKhoj',
                'email-templates.form.mentor-booking-mentee-confirmed',
                FormMailLogic::withBrandPublic(self::bookingContext($booking, confirmed: true))
            ));
            if (self::hasEmailTimestampColumn('mentee_confirmed_email_sent_at')) {
                $booking->mentee_confirmed_email_sent_at = now();
                $booking->save();
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('Mentor booking mentee confirmed email failed: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Email + WhatsApp after mentor sets date/time on a confirmed booking.
     */
    public static function sendScheduleConfirmedNotify(MentorBooking $booking, bool $force = false): bool
    {
        $booking->loadMissing(['mentor.user', 'service', 'mentee']);

        if ($booking->status !== 'confirmed' || !$booking->preferred_date || !$booking->preferred_time) {
            return false;
        }

        if (!$force && self::hasEmailTimestampColumn('schedule_notify_sent_at') && $booking->schedule_notify_sent_at) {
            return false;
        }

        $emailOk = false;
        if (FormMailLogic::isMailEnabled() && self::menteeEmail($booking)) {
            try {
                Mail::to(self::menteeEmail($booking))->cc(FormMailLogic::notifyEmail())->send(new FormSubmissionMail(
                    'Your MentorKhoj session time is confirmed | MentorKhoj',
                    'email-templates.form.mentor-booking-mentee-confirmed',
                    FormMailLogic::withBrandPublic(self::bookingContext($booking, confirmed: true))
                ));
                if (self::hasEmailTimestampColumn('mentee_confirmed_email_sent_at')) {
                    $booking->mentee_confirmed_email_sent_at = now();
                }
                $emailOk = true;
            } catch (\Throwable $e) {
                Log::warning('Schedule confirmed email failed: ' . $e->getMessage());
            }
        }

        $phone = trim((string) ($booking->mentee?->phone ?? ''));
        if ($phone !== '') {
            $date = $booking->preferred_date
                ? Carbon::parse($booking->preferred_date)->format('d M Y')
                : '';
            $time = self::formatSessionTime($booking->preferred_time);
            WhatsAppDemoBookingModule::sendSessionConfirmed(
                $phone,
                self::firstNameFromUser($booking->mentee),
                $date,
                $time
            );
        }

        if (self::hasEmailTimestampColumn('schedule_notify_sent_at')) {
            $booking->schedule_notify_sent_at = now();
            $booking->save();
        }

        return $emailOk;
    }

    /**
     * 24h-before reminder to mentor + student, Mentorkhoj on CC.
     */
    public static function sendSessionReminder24h(MentorBooking $booking): bool
    {
        if (!FormMailLogic::isMailEnabled()) {
            return false;
        }

        $booking->loadMissing(['mentor.user', 'service', 'mentee']);

        if (!in_array((string) $booking->status, ['requested', 'confirmed'], true)) {
            return false;
        }

        if (!$booking->preferred_date || !$booking->preferred_time) {
            return false;
        }

        if (self::hasEmailTimestampColumn('session_reminder_24h_sent_at') && $booking->session_reminder_24h_sent_at) {
            return false;
        }

        $when = SessionCreditLogic::sessionDateTime($booking);
        if (!$when) {
            return false;
        }

        $now = now();
        if ($when->lte($now) || $when->gt($now->copy()->addHours(24))) {
            return false;
        }

        $recipients = array_values(array_unique(array_filter([
            self::menteeEmail($booking),
            self::mentorEmail($booking),
        ])));

        if ($recipients === []) {
            return false;
        }

        try {
            Mail::to($recipients)
                ->cc(FormMailLogic::notifyEmail())
                ->send(new FormSubmissionMail(
                    'Reminder: your MentorKhoj session is in 24 hours | MentorKhoj',
                    'email-templates.form.mentor-booking-session-reminder-24h',
                    FormMailLogic::withBrandPublic(self::bookingContext($booking, confirmed: true))
                ));

            if (self::hasEmailTimestampColumn('session_reminder_24h_sent_at')) {
                $booking->session_reminder_24h_sent_at = now();
                $booking->save();
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('Session 24h reminder email failed: ' . $e->getMessage());

            return false;
        }
    }

    public static function maybeSendAfterPayment(MentorBooking $booking): void
    {
        if ($booking->payment_status !== 'paid') {
            return;
        }

        self::sendBookingPlacedEmails($booking);
    }

    public static function defaultPaymentLink(MentorBooking $booking): string
    {
        $siteUrl = rtrim((string) config('app.mentorkhoj_site_url', 'https://www.mentorkhoj.com'), '/');

        return $siteUrl . '/my-bookings/' . $booking->id;
    }

    public static function sendPaymentReminderEmail(MentorBooking $booking, ?string $paymentLink = null, ?string $overrideRecipient = null): bool
    {
        if (!FormMailLogic::isMailEnabled()) {
            Log::warning('Payment reminder mail skipped: SMTP not configured');

            return false;
        }

        $booking->loadMissing(['mentor.user', 'service', 'mentee']);

        if (!in_array($booking->payment_status, ['pending', 'failed'], true)) {
            return false;
        }

        $recipient = trim((string) ($overrideRecipient ?? ''));
        if ($recipient === '') {
            $recipient = self::menteeEmail($booking) ?? '';
        }
        if ($recipient === '') {
            return false;
        }

        $link = trim((string) ($paymentLink ?? ''));
        if ($link === '') {
            $link = self::defaultPaymentLink($booking);
        }

        $context = self::bookingContext($booking);
        $context['payment_link'] = $link;
        $context['amount_due'] = $context['amount_paid'];
        $context['amount_label'] = 'Amount due';

        try {
            Mail::to($recipient)
                ->cc(FormMailLogic::notifyEmail())
                ->send(new FormSubmissionMail(
                    'Complete payment for your MentorKhoj session | MentorKhoj',
                    'email-templates.form.mentor-booking-payment-reminder',
                    FormMailLogic::withBrandPublic($context)
                ));

            if (!$overrideRecipient && self::hasEmailTimestampColumn('payment_reminder_email_sent_at')) {
                $booking->payment_reminder_email_sent_at = now();
                $booking->save();
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('Payment reminder email failed: ' . $e->getMessage());

            return false;
        }
    }

    /** @return array<string, mixed> */
    public static function bookingContext(MentorBooking $booking, bool $forMentor = false, bool $confirmed = false): array
    {
        $booking->loadMissing(['mentor.user', 'service', 'mentee']);
        $service = $booking->service;
        $siteUrl = rtrim((string) config('app.mentorkhoj_site_url', 'https://www.mentorkhoj.com'), '/');
        $currency = Helpers::currency_symbol();
        $totalPaid = round((float) $booking->amount + (float) $booking->tax_amount, 2);
        $timezone = (string) (Helpers::get_business_settings('timezone') ?? 'Asia/Kolkata');
        $timezoneLabel = self::timezoneLabel($timezone);

        $sessionDate = $booking->preferred_date
            ? Carbon::parse($booking->preferred_date)->format('l, d M Y')
            : 'To be coordinated on MentorKhoj';

        $sessionTime = self::formatSessionTime($booking->preferred_time);

        return [
            'mentee_first_name' => self::firstNameFromUser($booking->mentee),
            'mentor_first_name' => self::firstNameFromMentor($booking->mentor),
            'session_title' => $service?->title ?? 'Mentorship session',
            'booking_date' => $booking->created_at?->timezone($timezone)->format('l, d M Y'),
            'session_date' => $sessionDate,
            'session_time' => $sessionTime,
            'time_zone' => $timezoneLabel,
            'session_duration' => self::formatDuration($service?->duration_minutes),
            'session_mode' => self::formatMeetingType($service?->meeting_type),
            'booking_id' => 'MK-' . str_pad((string) $booking->id, 6, '0', STR_PAD_LEFT),
            'amount_paid' => $totalPaid <= 0
                ? 'Free'
                : $currency . number_format($totalPaid, 2),
            'session_access_link' => $confirmed
                ? $siteUrl . '/account/sessions'
                : $siteUrl . '/account/sessions',
            'mentor_dashboard_link' => $siteUrl . '/mentor/dashboard/bookings',
            'support_email' => FormMailLogic::adminEmail(),
            'support_phone_primary' => '+91 73669 39888',
            'support_phone_secondary' => '+91 91026 95888',
            'for_mentor' => $forMentor,
            'confirmed' => $confirmed,
            'mentee_note' => trim((string) ($booking->mentee_note ?? '')),
        ];
    }

    private static function menteeEmail(MentorBooking $booking): ?string
    {
        $email = trim((string) ($booking->mentee?->email ?? ''));

        return $email !== '' ? $email : null;
    }

    private static function mentorEmail(MentorBooking $booking): ?string
    {
        $email = trim((string) ($booking->mentor?->user?->email ?? ''));

        return $email !== '' ? $email : null;
    }

    private static function firstNameFromUser(?User $user): string
    {
        $first = trim((string) ($user?->f_name ?? ''));

        return $first !== '' ? $first : 'there';
    }

    private static function firstNameFromMentor(?Mentor $mentor): string
    {
        $display = trim((string) ($mentor?->display_name ?? ''));
        if ($display === '') {
            return 'your mentor';
        }

        $parts = preg_split('/\s+/', $display) ?: [];

        return $parts[0] ?? 'your mentor';
    }

    private static function formatDuration(?int $minutes): string
    {
        $minutes = (int) ($minutes ?: 30);

        return $minutes . ' minute' . ($minutes === 1 ? '' : 's');
    }

    private static function formatMeetingType(?string $type): string
    {
        return match (strtolower((string) $type)) {
            'video' => 'Video call',
            'audio' => 'Audio call',
            'chat' => 'Chat session',
            'in_person' => 'In-person',
            default => ucfirst(str_replace('_', ' ', (string) ($type ?: 'video'))),
        };
    }

    private static function formatSessionTime(?string $time): string
    {
        $time = trim((string) $time);
        if ($time === '') {
            return 'To be coordinated on MentorKhoj';
        }

        try {
            return Carbon::createFromFormat('H:i:s', strlen($time) === 5 ? $time . ':00' : $time)->format('g:i A');
        } catch (\Throwable $e) {
            return $time;
        }
    }

    private static function timezoneLabel(string $timezone): string
    {
        try {
            return Carbon::now($timezone)->format('T') . ' (' . $timezone . ')';
        } catch (\Throwable $e) {
            return 'IST (Asia/Kolkata)';
        }
    }

    private static function hasEmailTimestampColumn(string $column): bool
    {
        return Schema::hasColumn('mentor_bookings', $column);
    }
}
