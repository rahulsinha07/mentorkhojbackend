<?php

namespace App\CentralLogics;

use App\Model\Mentor\MentorBooking;
use Carbon\Carbon;

class SessionIcsLogic
{
    public static function generate(MentorBooking $booking): string
    {
        $when = SessionCreditLogic::sessionDateTime($booking);
        if (!$when) {
            throw new \RuntimeException('Session has no scheduled date/time.');
        }

        $duration = (int) ($booking->duration_minutes ?? $booking->service?->duration_minutes ?? 30);
        $end = $when->copy()->addMinutes(max(5, $duration));

        $booking->loadMissing(['mentor', 'service', 'mentee']);
        $mentorName = $booking->mentor?->display_name ?? 'Mentor';
        $serviceTitle = $booking->service?->title ?? 'Mentor session';
        $summary = "{$serviceTitle} with {$mentorName}";
        $uid = "mentorkhoj-session-{$booking->id}@mentorkhoj.com";
        $description = $booking->meeting_url ? "Join: {$booking->meeting_url}" : 'MentorKhoj session';

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//MentorKhoj//Session//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            "UID:{$uid}",
            'DTSTAMP:'.self::icsUtc(now()),
            'DTSTART:'.self::icsUtc($when),
            'DTEND:'.self::icsUtc($end),
            'SUMMARY:'.self::escape($summary),
            'DESCRIPTION:'.self::escape($description),
            'LOCATION:'.self::escape($booking->meeting_url ?? 'Online'),
            'STATUS:CONFIRMED',
            'BEGIN:VALARM',
            'TRIGGER:-PT15M',
            'ACTION:DISPLAY',
            'DESCRIPTION:Session starting soon',
            'END:VALARM',
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return implode("\r\n", $lines)."\r\n";
    }

    public static function filename(MentorBooking $booking): string
    {
        return 'mentorkhoj-session-'.$booking->id.'.ics';
    }

    private static function icsUtc(Carbon $dt): string
    {
        return $dt->copy()->utc()->format('Ymd\THis\Z');
    }

    private static function escape(string $value): string
    {
        return str_replace(["\\", ";", ",", "\n"], ["\\\\", "\\;", "\\,", "\\n"], $value);
    }
}
