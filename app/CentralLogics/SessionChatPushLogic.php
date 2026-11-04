<?php

namespace App\CentralLogics;

use App\Model\SessionChatMessage;

class SessionChatPushLogic
{
    public static function notify(SessionChatMessage $message): void
    {
        try {
            $message->loadMissing(['mentee', 'mentor.user']);
            $mentee = $message->mentee;
            $mentor = $message->mentor;
            $mentorUser = $mentor?->user;
            $studentFirst = SessionChatLogic::studentFirstName($mentee);
            $mentorName = $mentor?->display_name ?: 'Mentor';
            $preview = SessionChatLogic::previewTextForMessage($message);
            if (strlen($preview) > 140) {
                $preview = substr($preview, 0, 137).'...';
            }

            $site = rtrim((string) config('app.mentorkhoj_site_url', 'https://www.mentorkhoj.com'), '/');
            $mentorId = (int) $message->mentor_id;
            $menteeUserId = (int) $message->mentee_user_id;

            if ($message->sender_role === 'mentee') {
                $recipientId = (int) ($mentorUser->id ?? 0);
                $title = $studentFirst.' messaged you';
                $link = $site.'/mentor/dashboard/messages?mentor='.$mentorId.'&mentee='.$menteeUserId;
            } else {
                $recipientId = $menteeUserId;
                $senderName = $message->sender_role === 'admin'
                    ? SessionChatLogic::supportMentorDisplayName()
                    : $mentorName;
                $title = $senderName.' messaged you';
                $link = $site.'/account/messages?mentor='.$mentorId.'&mentee='.$menteeUserId;
            }

            if ($recipientId <= 0) {
                return;
            }

            UserNotificationLogic::create(
                $recipientId,
                'session_message',
                $title,
                $preview !== '' ? $preview : 'Open the app to read it.',
                $link,
                $message->mentor_booking_id ? (int) $message->mentor_booking_id : null,
                [
                    'mentor_id' => (string) $mentorId,
                    'mentee_user_id' => (string) $menteeUserId,
                ]
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Session chat push failed: '.$e->getMessage());
        }
    }
}
