<?php

namespace App\Mail;

use App\Model\DemoBooking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Ops alert — same mail stack as seminar booking (Hostinger SMTP).
 */
class DemoBookedAdminAlert extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public DemoBooking $booking) {}

    public function build(): self
    {
        $label = $this->booking->category_label ?: $this->booking->category;
        $fromEmail = config('mail.from.address') ?: 'admin@mentorkhoj.com';
        $fromName = config('mail.from.name') ?: 'MentorKhoj';

        return $this->subject("[Demo] {$label} — {$this->booking->name} ({$this->booking->booking_ref})")
            ->from($fromEmail, $fromName)
            ->replyTo($fromEmail, 'MentorKhoj Support')
            ->view('emails.demo-booked-admin', [
                'booking' => $this->booking,
                'label' => $label,
            ]);
    }
}
