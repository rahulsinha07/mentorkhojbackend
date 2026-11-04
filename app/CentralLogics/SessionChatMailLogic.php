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
        $supportMentorId = SessionChatLogic::ensureSupportMentorId();
        $isSupportThread = (int) $message->mentor_id === $supportMentorId
            || SessionChatLogic::isSupportMentor((int) $message->mentor_id);

        try {
            if ($message->sender_role === 'admin') {
                $to = $mentee?->email;
                if (!$to) {
                    return;
                }
                $messagesLink = $site.'/account/messages?mentor='.$supportMentorId;
                $mail = new FormSubmissionMail(
                    'New message from MentorKhoj ADMIN',
                    'email-templates.form.session-chat-to-student',
                    FormMailLogic::withBrandPublic([
                        'student_first_name' => $studentFirst,
                        'mentor_name' => SessionChatLogic::supportMentorDisplayName(),
                        'body' => SessionChatLogic::emailBodyForMessage($message),
                        'sessions_link' => $messagesLink,
                        'cta_label' => 'Open messages',
                        'intro_line' => 'MentorKhoj ADMIN sent you a message.',
                        'brand' => $brand,
                        'support_email' => FormMailLogic::adminEmail(),
                    ])
                );
                Mail::to($to)->cc($cc)->send($mail);

                return;
            }

            if ($message->sender_role === 'mentee') {
                if ($isSupportThread) {
                    $to = $cc ?: FormMailLogic::adminEmail();
                    if (!$to) {
                        return;
                    }
                    $mail = new FormSubmissionMail(
                        'Student replied to ADMIN on MentorKhoj',
                        'email-templates.form.session-chat-to-mentor',
                        FormMailLogic::withBrandPublic([
                            'student_first_name' => $studentFirst,
                            'mentor_first_name' => 'Admin',
                            'body' => SessionChatLogic::emailBodyForMessage($message),
                            'bookings_link' => rtrim((string) config('app.url'), '/').'/admin/mentor/session-messages',
                            'brand' => $brand,
                            'support_email' => FormMailLogic::adminEmail(),
                        ])
                    );
                    Mail::to($to)->send($mail);

                    return;
                }

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
                        'body' => SessionChatLogic::emailBodyForMessage($message),
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
                    'body' => SessionChatLogic::emailBodyForMessage($message),
                    'sessions_link' => $site.'/account/messages?mentor='.(int) $message->mentor_id,
                    'cta_label' => 'Open messages',
                    'intro_line' => $mentorName.' replied to you on MentorKhoj.',
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
