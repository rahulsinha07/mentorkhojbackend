<?php

namespace App\Console\Commands;

use App\CentralLogics\MentorBookingMailLogic;
use App\CentralLogics\SessionCreditLogic;
use App\Model\Mentor\MentorBooking;
use Illuminate\Console\Command;

class SendSessionReminders24h extends Command
{
    protected $signature = 'mentorkhoj:send-session-reminders-24h';

    protected $description = 'Email mentor + student (CC Mentorkhoj) ~24 hours before each upcoming session';

    public function handle(): int
    {
        $sent = 0;
        $skipped = 0;

        $bookings = MentorBooking::query()
            ->with(['mentor.user', 'service', 'mentee'])
            ->whereIn('status', ['requested', 'confirmed'])
            ->whereNotNull('preferred_date')
            ->whereNotNull('preferred_time')
            ->whereNull('session_reminder_24h_sent_at')
            ->whereDate('preferred_date', '>=', now()->subDay()->toDateString())
            ->whereDate('preferred_date', '<=', now()->addDay()->toDateString())
            ->orderBy('id')
            ->get();

        foreach ($bookings as $booking) {
            $when = SessionCreditLogic::sessionDateTime($booking);
            if (!$when || $when->lte(now()) || $when->gt(now()->addHours(24))) {
                $skipped++;
                continue;
            }

            if (MentorBookingMailLogic::sendSessionReminder24h($booking)) {
                $sent++;
            } else {
                $skipped++;
            }
        }

        $this->info("Session 24h reminders sent={$sent} skipped={$skipped}");

        return self::SUCCESS;
    }
}
