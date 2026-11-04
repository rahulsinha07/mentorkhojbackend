<?php

namespace App\Console\Commands;

use App\CentralLogics\Helpers;
use App\CentralLogics\MentorBookingMailLogic;
use App\CentralLogics\SessionCreditLogic;
use App\Model\Mentor\MentorBooking;
use Illuminate\Console\Command;

class SendSessionReminders1h extends Command
{
    protected $signature = 'mentorkhoj:send-session-reminders-1h';

    protected $description = 'Email mentor + student ~1 hour before each upcoming session (when enabled)';

    public function handle(): int
    {
        $enabled = (int) (Helpers::get_business_settings('session_reminder_1h_enabled') ?? 0);
        if ($enabled !== 1) {
            $this->info('Session 1h reminders disabled (session_reminder_1h_enabled=0)');

            return self::SUCCESS;
        }

        $sent = 0;
        $skipped = 0;

        $bookings = MentorBooking::query()
            ->with(['mentor.user', 'service', 'mentee'])
            ->where('status', 'confirmed')
            ->whereNotNull('scheduled_at')
            ->orderBy('id')
            ->get();

        foreach ($bookings as $booking) {
            $when = SessionCreditLogic::sessionDateTime($booking);
            if (!$when || $when->lte(now()) || $when->gt(now()->addHour())) {
                $skipped++;
                continue;
            }

            if (MentorBookingMailLogic::sendSessionReminder1h($booking)) {
                $sent++;
            } else {
                $skipped++;
            }
        }

        $this->info("Session 1h reminders sent={$sent} skipped={$skipped}");

        return self::SUCCESS;
    }
}
