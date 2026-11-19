<?php

namespace App\Console\Commands;

use App\CentralLogics\SessionCreditLogic;
use App\Model\Mentor\MentorBooking;
use App\Services\ZohoMeetingService;
use Illuminate\Console\Command;

/**
 * One-off backfill for bookings created before zoho_start_link existed.
 * New bookings get zoho_start_link directly from createMeeting()'s response
 * (see MentorSessionLifecycleLogic); this only needs to run once, by hand,
 * to pick up sessions scheduled before that change shipped. Safe to re-run.
 */
class BackfillZohoStartLinks extends Command
{
    protected $signature = 'mentorkhoj:backfill-zoho-start-links';

    protected $description = 'Backfill zoho_start_link for upcoming Zoho sessions that were scheduled before it existed';

    public function handle(ZohoMeetingService $zoho): int
    {
        if (!$zoho->isConnected()) {
            $this->info('Zoho not connected; nothing to backfill.');

            return self::SUCCESS;
        }

        $candidates = MentorBooking::query()
            ->whereNotNull('zoho_meeting_id')
            ->where('meeting_link_source', 'zoho')
            ->whereNull('zoho_start_link')
            ->get();

        $filled = 0;
        $skippedPast = 0;
        $failed = 0;

        foreach ($candidates as $booking) {
            $when = SessionCreditLogic::sessionDateTime($booking);
            if (!$when || $when->lte(now())) {
                $skippedPast++;
                continue;
            }

            $startLink = $zoho->getStartLink((string) $booking->zoho_meeting_id);

            if ($startLink) {
                $booking->zoho_start_link = $startLink;
                $booking->save();
                $filled++;
            } else {
                $failed++;
            }
        }

        $this->info("Zoho start-link backfill: filled={$filled} skipped_past={$skippedPast} failed={$failed}");

        return self::SUCCESS;
    }
}
