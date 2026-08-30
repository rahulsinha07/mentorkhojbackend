<?php

namespace App\Mail;

use App\Model\DemoBooking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DemoMentorAssigned extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public DemoBooking $booking,
        public string $ctaUrl,
        public bool $hasAccount,
        public bool $ccOps = true,
    ) {
    }

    public function build(): self
    {
        $fromEmail = config('mail.from.address') ?: 'admin@mentorkhoj.com';
        $fromName = config('mail.from.name') ?: 'MentorKhoj';
        $cc = env('MENTORKHOJ_NOTIFY_EMAIL', 'mentorkhoj@gmail.com');

        $mail = $this->subject('Your MentorKhoj mentor is ready')
            ->from($fromEmail, $fromName)
            ->replyTo($fromEmail, 'MentorKhoj Support')
            ->view('emails.demo-mentor-assigned', [
                'booking' => $this->booking,
                'ctaUrl' => $this->ctaUrl,
                'hasAccount' => $this->hasAccount,
            ]);

        if ($this->ccOps && $cc) {
            $mail->cc($cc);
        }

        return $mail;
    }
}
