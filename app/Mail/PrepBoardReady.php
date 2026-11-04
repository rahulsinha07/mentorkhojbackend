<?php

namespace App\Mail;

use App\CentralLogics\PrepBoardLogic;
use App\Model\Mentor\PrepBoard;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PrepBoardReady extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PrepBoard $board) {}

    public function build(): self
    {
        $this->board->loadMissing(['mentee']);
        $fromEmail = config('mail.from.address') ?: 'admin@mentorkhoj.com';
        $fromName = config('mail.from.name') ?: 'MentorKhoj';

        // No mentor name / phone / email in subject or body (PII-safe coach email).
        return $this->subject('Your study plan is ready | MentorKhoj')
            ->from($fromEmail, $fromName)
            ->replyTo($fromEmail, 'MentorKhoj Support')
            ->view('emails.prep-board-ready', [
                'board' => $this->board,
                'studentName' => $this->board->mentee?->f_name ?: 'there',
                'link' => PrepBoardLogic::boardLink($this->board, false),
            ]);
    }
}
