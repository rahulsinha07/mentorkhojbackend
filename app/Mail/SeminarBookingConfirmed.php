<?php

namespace App\Mail;

use App\Model\Seminar\SeminarBooking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SeminarBookingConfirmed extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public SeminarBooking $booking) {}

    public function build(): self
    {
        $seminar = $this->booking->seminar;
        $paid = $this->booking->payment_status === 'paid';

        return $this->subject("You're registered — {$seminar->title} | MentorKhoj")
            ->view('emails.seminar-booking-confirmed', [
                'booking' => $this->booking,
                'seminar' => $seminar,
                'paid' => $paid,
            ]);
    }
}
