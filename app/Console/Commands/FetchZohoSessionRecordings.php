<?php

namespace App\Console\Commands;

use App\CentralLogics\SessionCreditLogic;
use App\Model\Mentor\MentorBooking;
use App\Services\ZohoMeetingService;
use Illuminate\Console\Command;

/**
 * Polls Zoho for the recording of each session that ended recently and has
 * a Zoho-generated meeting (zoho_meeting_id set, meeting_link_source=zoho),
 * attaching the play/download URL and transcript/summary once Zoho has
 * finished processing them.
 *
 * Zoho has no webhook for "recording ready", so this runs on a schedule and
 * retries a bounded number of times — processing can take Zoho a few minutes
 * to a few hours after a call ends.
 */
class FetchZohoSessionRecordings extends Command
{
    protected $signature = 'mentorkhoj:fetch-zoho-recordings';

    protected $description = 'Fetch Zoho Meeting recordings/transcripts for recently completed sessions';

    private const MAX_ATTEMPTS = 20; // ~ a few days at one run per hour before giving up
    private const MIN_MINUTES_AFTER_END = 10; // give Zoho time to start processing

    public function handle(ZohoMeetingService $zoho): int
    {
        if (!$zoho->isConnected()) {
            $this->info('Zoho not connected; skipping recording fetch.');

            return self::SUCCESS;
        }

        $candidates = MentorBooking::query()
            ->whereNotNull('zoho_meeting_id')
            ->where('meeting_link_source', 'zoho')
            ->whereNull('recording_play_url')
            ->where(function ($q) {
                $q->whereNull('zoho_recording_fetch_status')
                    ->orWhere('zoho_recording_fetch_status', 'pending')
                    ->orWhere('zoho_recording_fetch_status', 'error');
            })
            ->where('zoho_recording_fetch_attempts', '<', self::MAX_ATTEMPTS)
            ->orderBy('id')
            ->limit(200)
            ->get();

        $found = 0;
        $pending = 0;
        $gaveUp = 0;

        foreach ($candidates as $booking) {
            $endedAt = $this->sessionEndedAt($booking);
            if (!$endedAt || $endedAt->gt(now()->subMinutes(self::MIN_MINUTES_AFTER_END))) {
                continue; // session hasn't happened yet, or ended too recently to have a recording
            }

            $booking->zoho_recording_fetch_attempts = (int) $booking->zoho_recording_fetch_attempts + 1;

            $recording = $zoho->fetchRecording((string) $booking->zoho_meeting_id);

            if ($recording) {
                $booking->recording_play_url = $recording['play_url'];
                $booking->recording_download_url = $recording['download_url'];
                $booking->recording_duration_seconds = $recording['duration_seconds'];
                $booking->transcript_text = $recording['transcript_text'];
                $booking->transcript_language = $recording['transcript_language'];
                $booking->summary_text = $recording['summary_text'];
                $booking->zoho_recording_fetch_status = 'found';
                $found++;
            } elseif ($booking->zoho_recording_fetch_attempts >= self::MAX_ATTEMPTS) {
                $booking->zoho_recording_fetch_status = 'not_found';
                $gaveUp++;
            } else {
                $booking->zoho_recording_fetch_status = 'pending';
                $pending++;
            }

            $booking->save();
        }

        $this->info("Zoho recording fetch: found={$found} still_pending={$pending} gave_up={$gaveUp}");

        return self::SUCCESS;
    }

    private function sessionEndedAt(MentorBooking $booking): ?\Carbon\Carbon
    {
        $start = SessionCreditLogic::sessionDateTime($booking);
        if (!$start) {
            return null;
        }

        $minutes = (int) ($booking->duration_minutes ?: 30);

        return $start->copy()->addMinutes($minutes);
    }
}
