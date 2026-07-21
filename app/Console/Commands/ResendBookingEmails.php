<?php

namespace App\Console\Commands;

use App\CentralLogics\MentorBookingLogic;
use App\Model\Mentor\MentorBooking;
use App\Model\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class ResendBookingEmails extends Command
{
    protected $signature = 'mentorkhoj:resend-booking-emails
        {--booking-id= : Resend for one booking only}
        {--sync-paid : Sync pending bookings whose legacy orders are already paid}';

    protected $description = 'Resend mentor booking confirmation emails and sync paid bookings stuck in pending';

    public function handle(): int
    {
        if ($this->option('sync-paid')) {
            $synced = 0;
            $pending = MentorBooking::where('payment_status', 'pending')
                ->whereNotNull('legacy_order_id')
                ->get();

            foreach ($pending as $booking) {
                $order = Order::find($booking->legacy_order_id);
                if ($order && in_array((string) $order->payment_status, ['paid', 'partially_paid'], true)) {
                    MentorBookingLogic::syncFromLegacyOrder($booking, $order);
                    $synced++;
                    $this->line("Synced booking #{$booking->id} from paid order #{$order->id}");
                }
            }

            $this->info("Synced {$synced} pending booking(s) from paid orders.");
        }

        $bookingId = $this->option('booking-id') ? (int) $this->option('booking-id') : null;
        $result = MentorBookingLogic::resendMissingBookingEmails($bookingId);

        $this->info('Mentee confirmation emails sent: ' . $result['mentee_emails_sent']);
        $this->info('Mentor notification emails sent: ' . $result['mentor_emails_sent']);

        if (!Schema::hasColumn('mentor_bookings', 'mentee_booked_email_sent_at')) {
            $this->warn('Email timestamp columns are missing. Run: php artisan migrate');
        }

        return self::SUCCESS;
    }
}
