<?php

namespace App\Mail\Form;

use App\CentralLogics\FormMailLogic;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FormSubmissionMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param array<string, mixed> $data */
    public function __construct(
        public string $mailSubject,
        public string $template,
        public array $data
    ) {}

    public function build(): self
    {
        $fromEmail = config('mail.from.address') ?: FormMailLogic::adminEmail();

        return $this->subject($this->mailSubject)
            ->from($fromEmail, 'MentorKhoj')
            ->replyTo(FormMailLogic::adminEmail(), 'MentorKhoj Support')
            ->view($this->template, $this->data);
    }
}
