<?php

namespace App\CentralLogics;

use App\Mail\Form\FormSubmissionMail;
use App\Model\SessionChatMessage;
use App\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SessionChatMailLogic
{
    public static function notify(SessionChatMessage $message): void
    {
        if (!FormMailLogic::isMailEnabled()) {
            return;
        }

        $message->loadMissing(['mentee', 'mentor.user']);
        $mentee = $message->mentee;
        $mentor = $message->mentor;
        $mentorUser = $mentor?->user;
        $studentFirst = SessionChatLogic::studentFirstName($mentee);
        $mentorName = $mentor?->display_name ?: 'Mentor';
        $brand = FormMailLogic::brandContext();
        $cc = FormMailLogic::notifyEmail();
        $site = rtrim((string) ($brand['site_url'] ?? 'https://www.mentorkhoj.com'), '/');

        try {
            if ($message->sender_role === 'mentee') {
                $to = $mentorUser?->email;
                if (!$to) {
                    return;
                }
                $mail = new FormSubmissionMail(
                    'New message from '.$studentFirst.' on MentorKhoj',
                    'email-templates.form.session-chat-to-mentor',
                    FormMailLogic::withBrandPublic([
                        'student_first_name' => $studentFirst,
                        'mentor_first_name' => SessionChatLogic::firstName($mentorUser->f_name ?? $mentorName),
                        'body' => $message->body,
                        'bookings_link' => $site.'/mentor/dashboard/bookings',
                        'brand' => $brand,
                        'support_email' => FormMailLogic::adminEmail(),
                    ])
                );
                Mail::to($to)->cc($cc)->send($mail);
                return;
            }

            $to = $mentee?->email;
            if (!$to) {
                return;
            }
            $mail = new FormSubmissionMail(
                'New message from '.$mentorName.' on MentorKhoj',
                'email-templates.form.session-chat-to-student',
                FormMailLogic::withBrandPublic([
                    'student_first_name' => $studentFirst,
                    'mentor_name' => $mentorName,
                    'body' => $message->body,
                    'sessions_link' => $site.'/account/sessions',
                    'brand' => $brand,
                    'support_email' => FormMailLogic::adminEmail(),
                ])
            );
            Mail::to($to)->cc($cc)->send($mail);
        } catch (\Throwable $e) {
            Log::warning('Session chat email failed: '.$e->getMessage());
        }
    }
}
