<?php

namespace App\CentralLogics;

use App\Mail\Form\FormSubmissionMail;
use App\Model\Mentor\Mentor;
use App\Model\Mentor\MentorBooking;
use App\Model\Mentor\MentorSessionCredit;
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
                self::sendWithMentorkhojCopy(
                    self::menteeEmail($booking),
                    'Your MentorKhoj session is booked | MentorKhoj',
                    'email-templates.form.mentor-booking-mentee-booked',
                    self::bookingContext($booking)
                );
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
                self::sendWithMentorkhojCopy(
                    self::mentorEmail($booking),
                    'New session booking — please confirm | MentorKhoj',
                    'email-templates.form.mentor-booking-mentor-confirm',
                    self::bookingContext($booking, forMentor: true)
                );
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

        $date = $booking->preferred_date
            ? Carbon::parse($booking->preferred_date)->format('d M Y')
            : '';
        $time = self::formatSessionTime($booking->preferred_time);
        self::sendWhatsAppSessionScheduled($booking, $date, $time);

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

        if (!in_array((string) $booking->status, ['requested', 'confirmed', 'reschedule_requested'], true)) {
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

        $scheduledFor = $booking->scheduled_at
            ? Carbon::parse($booking->scheduled_at)
            : $when->copy()->utc();

        $claim = MentorSessionNotificationLogic::claimNotification(
            (int) $booking->id,
            null,
            'session_reminder_24h',
            $scheduledFor
        );
        if (!$claim || !MentorSessionNotificationLogic::shouldSend($claim)) {
            return false;
        }

        $recipients = array_values(array_unique(array_filter([
            self::menteeEmail($booking),
            self::mentorEmail($booking),
        ])));

        if ($recipients === []) {
            MentorSessionNotificationLogic::markFailed($claim, 'No recipient emails');

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

            MentorSessionNotificationLogic::markSent($claim);

            if (self::hasEmailTimestampColumn('session_reminder_24h_sent_at')) {
                $booking->session_reminder_24h_sent_at = now();
                $booking->save();
            }

            return true;
        } catch (\Throwable $e) {
            MentorSessionNotificationLogic::markFailed($claim, $e->getMessage());
            Log::warning('Session 24h reminder email failed: ' . $e->getMessage());

            return false;
        }
    }

    public static function sendPackageAssignedEmails(MentorSessionCredit $credit): bool
    {
        if (!FormMailLogic::isMailEnabled()) {
            return false;
        }

        $credit->loadMissing(['mentor.user', 'mentee']);
        $context = self::packageContext($credit);
        $sent = false;

        if (self::menteeEmailFromUser($credit->mentee)) {
            try {
                Mail::to(self::menteeEmailFromUser($credit->mentee))->send(new FormSubmissionMail(
                    'Your session pack is ready | MentorKhoj',
                    'email-templates.sessions.package-assigned-mentee',
                    FormMailLogic::withBrandPublic($context)
                ));
                $sent = true;
            } catch (\Throwable $e) {
                Log::warning('Package assigned mentee email failed: ' . $e->getMessage());
            }
        }

        $mentorEmail = trim((string) ($credit->mentor?->user?->email ?? ''));
        if ($mentorEmail !== '') {
            try {
                Mail::to($mentorEmail)->send(new FormSubmissionMail(
                    'New session pack assigned | MentorKhoj',
                    'email-templates.sessions.package-assigned-mentor',
                    FormMailLogic::withBrandPublic(array_merge($context, ['for_mentor' => true]))
                ));
                $sent = true;
            } catch (\Throwable $e) {
                Log::warning('Package assigned mentor email failed: ' . $e->getMessage());
            }
        }

        return $sent;
    }

    public static function sendRescheduleRequestedToMentor(MentorBooking $booking): bool
    {
        if (!FormMailLogic::isMailEnabled()) {
            return false;
        }

        $booking->loadMissing(['mentor.user', 'service', 'mentee']);
        if (!self::mentorEmail($booking)) {
            return false;
        }

        try {
            Mail::to(self::mentorEmail($booking))->send(new FormSubmissionMail(
                'Reschedule request from your mentee | MentorKhoj',
                'email-templates.sessions.reschedule-requested',
                FormMailLogic::withBrandPublic(self::bookingContext($booking, forMentor: true))
            ));

            return true;
        } catch (\Throwable $e) {
            Log::warning('Reschedule requested email failed: ' . $e->getMessage());

            return false;
        }
    }

    public static function sendRescheduleApproved(MentorBooking $booking): bool
    {
        if (!FormMailLogic::isMailEnabled()) {
            return false;
        }

        $booking->loadMissing(['mentor.user', 'service', 'mentee']);
        if (!self::menteeEmail($booking)) {
            return false;
        }

        try {
            Mail::to(self::menteeEmail($booking))->send(new FormSubmissionMail(
                'Your reschedule request was approved | MentorKhoj',
                'email-templates.sessions.reschedule-approved',
                FormMailLogic::withBrandPublic(self::bookingContext($booking, confirmed: true))
            ));

            return true;
        } catch (\Throwable $e) {
            Log::warning('Reschedule approved email failed: ' . $e->getMessage());

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
        $currency = Helpers::currency_symbol();
        // Payment reminders must show what the student owes (platform fee + GST for free sessions).
        $amountDue = round((float) $booking->amount + (float) $booking->tax_amount, 2);
        $context['payment_link'] = $link;
        $context['amount_paid'] = $amountDue <= 0
            ? 'Free'
            : $currency . number_format($amountDue, 2);
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

    /**
     * Free mentor sessions still collect a Mentorkhoj platform fee + GST for admin.
     * That fee must not appear as "amount paid" to mentor or mentee in booking emails.
     */
    public static function isFreeSessionBooking(MentorBooking $booking): bool
    {
        $booking->loadMissing(['service']);
        $servicePrice = (float) ($booking->service?->price ?? -1);
        if ($servicePrice <= 0) {
            return true;
        }

        $amount = round((float) $booking->amount, 2);
        if ($amount <= 0) {
            return true;
        }

        $mentorNet = round((float) ($booking->mentor_net ?? 0), 2);
        $platformFee = round((float) ($booking->platform_fee ?? 0), 2);

        // Platform-fee-only booking: mentor earns 0; amount equals Mentorkhoj fee.
        return $mentorNet <= 0 && $platformFee > 0 && abs($platformFee - $amount) < 0.011;
    }

    /** @return array<string, mixed> */
    public static function bookingContext(MentorBooking $booking, bool $forMentor = false, bool $confirmed = false): array
    {
        $booking->loadMissing(['mentor.user', 'service', 'mentee']);
        $service = $booking->service;
        $siteUrl = rtrim((string) config('app.mentorkhoj_site_url', 'https://www.mentorkhoj.com'), '/');
        $currency = Helpers::currency_symbol();
        $isFreeSession = self::isFreeSessionBooking($booking);
        // Never include GST (tax_amount). Free sessions hide the admin platform fee entirely.
        $sessionAmount = round((float) $booking->amount, 2);
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
            'amount_label' => $isFreeSession ? 'Session' : 'Amount paid',
            'amount_paid' => $isFreeSession
                ? 'Free session booked'
                : ($sessionAmount <= 0
                    ? 'Free'
                    : $currency . number_format($sessionAmount, 2)),
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
            'is_free_session' => $isFreeSession,
            'meeting_url' => $booking->meeting_url,
        ];
    }

    /** @return array<string, mixed> */
    public static function packageContext(MentorSessionCredit $credit): array
    {
        $credit->loadMissing(['mentor', 'mentee']);
        $siteUrl = rtrim((string) config('app.mentorkhoj_site_url', 'https://www.mentorkhoj.com'), '/');

        return [
            'mentee_first_name' => self::firstNameFromUser($credit->mentee),
            'mentor_first_name' => self::firstNameFromMentor($credit->mentor),
            'sessions_total' => (int) $credit->credits_total,
            'sessions_remaining' => $credit->remaining(),
            'session_access_link' => $siteUrl . '/account/sessions',
            'mentor_dashboard_link' => $siteUrl . '/mentor/dashboard/bookings',
            'support_email' => FormMailLogic::adminEmail(),
            'support_phone_primary' => '+91 73669 39888',
            'support_phone_secondary' => '+91 91026 95888',
        ];
    }

    /**
     * Send a booking mail and copy MentorKhoj unless that inbox is already the recipient.
     *
     * @param array<string, mixed> $context
     */
    private static function sendWithMentorkhojCopy(string $to, string $subject, string $view, array $context): void
    {
        $pending = Mail::to($to);
        $notify = trim(FormMailLogic::notifyEmail());
        if ($notify !== '' && strcasecmp($notify, $to) !== 0) {
            $pending->cc($notify);
        }

        $pending->send(new FormSubmissionMail(
            $subject,
            $view,
            FormMailLogic::withBrandPublic($context)
        ));
    }

    private static function menteeEmailFromUser(?User $user): ?string
    {
        $email = trim((string) ($user?->email ?? ''));

        return $email !== '' ? $email : null;
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

    /**
     * WhatsApp both sides once a session's date/time/join link is set —
     * mentee and mentor each get the other's name, the time, the join link,
     * and a link to their own dashboard for full details.
     */
    private static function sendWhatsAppSessionScheduled(MentorBooking $booking, string $date, string $time): void
    {
        $ctx = self::bookingContext($booking, confirmed: true);
        $menteeName = self::firstNameFromUser($booking->mentee);
        $mentorName = self::firstNameFromMentor($booking->mentor);
        $joinLink = trim((string) ($booking->meeting_url ?? '')) ?: 'will be shared shortly';

        $menteePhone = trim((string) ($booking->mentee?->phone ?? ''));
        if ($menteePhone !== '') {
            WhatsAppDemoBookingModule::sendSessionScheduled(
                $menteePhone,
                $menteeName,
                $mentorName,
                $date,
                $time,
                $joinLink,
                $ctx['session_access_link']
            );
        }

        $mentorPhone = trim((string) ($booking->mentor?->user?->phone ?? ''));
        if ($mentorPhone !== '') {
            WhatsAppDemoBookingModule::sendSessionScheduled(
                $mentorPhone,
                $mentorName,
                $menteeName,
                $date,
                $time,
                $joinLink,
                $ctx['mentor_dashboard_link']
            );
        }
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


    public static function sendSessionReminder1h(MentorBooking $booking): bool
    {
        if (!FormMailLogic::isMailEnabled()) {
            return false;
        }

        $booking->loadMissing(['mentor.user', 'service', 'mentee']);

        if ((string) $booking->status !== 'confirmed') {
            return false;
        }

        $when = SessionCreditLogic::sessionDateTime($booking);
        if (!$when || $when->lte(now()) || $when->gt(now()->copy()->addHour())) {
            return false;
        }

        $scheduledFor = $booking->scheduled_at
            ? Carbon::parse($booking->scheduled_at)
            : $when->copy()->utc();

        $claim = MentorSessionNotificationLogic::claimNotification(
            (int) $booking->id,
            null,
            'session_reminder_1h',
            $scheduledFor
        );
        if (!$claim || !MentorSessionNotificationLogic::shouldSend($claim)) {
            return false;
        }

        $recipients = array_values(array_unique(array_filter([
            self::menteeEmail($booking),
            self::mentorEmail($booking),
        ])));

        if ($recipients === []) {
            MentorSessionNotificationLogic::markFailed($claim, 'No recipient emails');

            return false;
        }

        try {
            Mail::to($recipients)
                ->cc(FormMailLogic::notifyEmail())
                ->send(new FormSubmissionMail(
                    'Reminder: your MentorKhoj session starts in 1 hour | MentorKhoj',
                    'email-templates.sessions.session-reminder-1h',
                    FormMailLogic::withBrandPublic(self::bookingContext($booking, confirmed: true))
                ));

            MentorSessionNotificationLogic::markSent($claim);

            if (self::hasEmailTimestampColumn('session_reminder_1h_sent_at')) {
                $booking->session_reminder_1h_sent_at = now();
                $booking->save();
            }

            return true;
        } catch (\Throwable $e) {
            MentorSessionNotificationLogic::markFailed($claim, $e->getMessage());
            Log::warning('Session 1h reminder email failed: ' . $e->getMessage());

            return false;
        }
    }

    public static function sendSessionCancelled(MentorBooking $booking, string $reason, bool $slotRestored = false): bool
    {
        if (!FormMailLogic::isMailEnabled()) {
            return false;
        }

        $booking->loadMissing(['mentor.user', 'service', 'mentee']);
        $context = self::bookingContext($booking, confirmed: true);
        $context['cancellation_reason'] = $reason;
        $context['slot_restored'] = $slotRestored;
        $sent = false;

        if (self::menteeEmail($booking)) {
            try {
                Mail::to(self::menteeEmail($booking))->send(new FormSubmissionMail(
                    $slotRestored
                        ? 'Your session slot was released | MentorKhoj'
                        : 'Your MentorKhoj session was cancelled | MentorKhoj',
                    'email-templates.sessions.session-cancelled',
                    FormMailLogic::withBrandPublic($context)
                ));
                $sent = true;
            } catch (\Throwable $e) {
                Log::warning('Session cancelled mentee email failed: ' . $e->getMessage());
            }
        }

        if (self::mentorEmail($booking)) {
            try {
                Mail::to(self::mentorEmail($booking))->send(new FormSubmissionMail(
                    $slotRestored
                        ? 'Session slot released to pending schedule | MentorKhoj'
                        : 'MentorKhoj session cancelled | MentorKhoj',
                    'email-templates.sessions.session-cancelled',
                    FormMailLogic::withBrandPublic(array_merge($context, ['for_mentor' => true]))
                ));
                $sent = true;
            } catch (\Throwable $e) {
                Log::warning('Session cancelled mentor email failed: ' . $e->getMessage());
            }
        }

        return $sent;
    }

}
