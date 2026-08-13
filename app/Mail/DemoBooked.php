<?php

namespace App\Mail;

use App\Model\DemoBooking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Student confirmation — same mail stack as SeminarBookingConfirmed
 * (Hostinger SMTP via config/mail.php).
 */
class DemoBooked extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public DemoBooking $booking) {}

    public function build(): self
    {
        $label = $this->booking->category_label ?: $this->booking->category;
        $fromEmail = config('mail.from.address') ?: 'admin@mentorkhoj.com';
        $fromName = config('mail.from.name') ?: 'MentorKhoj';

        return $this->subject("Demo booked — {$label} | MentorKhoj")
            ->from($fromEmail, $fromName)
            ->replyTo($fromEmail, 'MentorKhoj Support')
            ->view('emails.demo-booked', [
                'booking' => $this->booking,
                'label' => $label,
            ]);
    }
}
