<?php

namespace App\CentralLogics;

use App\Mail\Form\FormSubmissionMail;
use App\Model\Mentor\MentorBooking;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MentorFeedbackMailLogic
{
    /**
     * Sent when a mentor requests feedback on a completed session. Goes
     * through MentorKhoj's own sending identity and CC's the MentorKhoj
     * notify address (same pattern as every other mentor<->mentee email) so
     * there is never direct mentor-mentee email contact.
     */
    public static function sendFeedbackRequest(MentorBooking $booking): bool
    {
        if (!FormMailLogic::isMailEnabled()) {
            Log::warning('Feedback request mail skipped: SMTP not configured');

            return false;
        }

        $booking->loadMissing(['mentor', 'mentee', 'service']);
        $to = trim((string) ($booking->mentee?->email ?? ''));
        if ($to === '') {
            return false;
        }

        $siteUrl = rtrim((string) config('app.mentorkhoj_site_url', 'https://www.mentorkhoj.com'), '/');
        $mentorFirstName = self::firstName($booking->mentor?->display_name ?? '');
        $menteeFirstName = self::firstName($booking->mentee?->f_name ?? '');

        $context = FormMailLogic::withBrandPublic([
            'mentee_first_name' => $menteeFirstName !== '' ? $menteeFirstName : 'there',
            'mentor_first_name' => $mentorFirstName !== '' ? $mentorFirstName : 'Your mentor',
            'session_title' => $booking->service?->title ?? 'your mentorship session',
            'feedback_link' => "{$siteUrl}/account/sessions/{$booking->id}?feedback=1",
        ]);

        try {
            $pending = Mail::to($to);
            $notify = trim(FormMailLogic::notifyEmail());
            if ($notify !== '' && strcasecmp($notify, $to) !== 0) {
                $pending->cc($notify);
            }

            $pending->send(new FormSubmissionMail(
                "{$context['mentor_first_name']} would love your feedback | MentorKhoj",
                'email-templates.form.mentor-feedback-request',
                $context
            ));

            return true;
        } catch (\Throwable $e) {
            Log::warning('Feedback request mail failed: '.$e->getMessage());

            return false;
        }
    }

    private static function firstName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];

        return $parts[0] ?? '';
    }
}
