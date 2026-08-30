<?php

namespace App\CentralLogics;

class WhatsAppWebLink
{
    public static function digits(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone) ?? '';
        if ($digits === '') {
            return null;
        }
        if (strlen($digits) === 10) {
            $digits = '91'.$digits;
        }

        return $digits;
    }

    /**
     * Cross-device WhatsApp deep link.
     * Uses wa.me so phones open the WhatsApp app; desktops open WhatsApp Web/Desktop.
     * (web.whatsapp.com/send often fails or is unusable on mobile browsers.)
     */
    public static function url(?string $phone, string $text): ?string
    {
        $digits = self::digits($phone);
        if ($digits === null) {
            return null;
        }

        return 'https://wa.me/'.$digits.'?text='.rawurlencode($text);
    }

    public static function studentWelcome(string $name, ?string $program = null, ?string $ref = null, ?string $profileUrl = null): string
    {
        $greeting = trim($name) !== '' ? trim($name) : 'there';
        if ($program) {
            $refBit = $ref ? " ({$ref})" : '';
            $nameLine = trim($name) !== '' ? trim($name) : '';

            $text = "Hi {$greeting},\n\n"
                ."This is MentorKhoj. Thanks for booking your *{$program}* demo{$refBit}.\n\n"
                ."We'd love to walk you through how 1:1 mentorship can help with {$program}, and lock a demo slot that works for you.\n\n"
                ."To match you with the right mentor, could you share a few details when you reply?\n\n"
                ."Name : {$nameLine}\n"
                ."Class : \n"
                ."Place : \n"
                .'Reason for mentorship : ';

            if ($profileUrl) {
                $text .= "\n\nCreate your student profile here:\n{$profileUrl}";
            }

            return $text;
        }

        return "Hi {$greeting},\n\n"
            ."Welcome to MentorKhoj! You're set up as a student. We can help you find the right mentor and book a session.\n\n"
            .'Reply here anytime — happy to help.';
    }

    public static function mentorWelcome(string $name, ?string $sessionNote = null): string
    {
        $greeting = trim($name) !== '' ? trim($name) : 'there';
        $tail = $sessionNote
            ? "\n\n{$sessionNote}"
            : "\n\nReply here if you need help with your profile, sessions, or payouts.";

        return "Hi {$greeting},\n\n"
            ."Welcome to MentorKhoj! Thanks for joining as a mentor. We're glad to have you on the platform."
            .$tail;
    }
}
