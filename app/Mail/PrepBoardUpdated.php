<?php

namespace App\Mail;

use App\CentralLogics\PrepBoardLogic;
use App\Model\Mentor\PrepBoard;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PrepBoardUpdated extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PrepBoard $board) {}

    public function build(): self
    {
        $this->board->loadMissing(['mentor', 'mentee']);
        $mentorName = $this->board->mentor?->display_name ?: 'Your mentor';
        $fromEmail = config('mail.from.address') ?: 'admin@mentorkhoj.com';
        $fromName = config('mail.from.name') ?: 'MentorKhoj';

        return $this->subject("PrepBoard updated · {$mentorName} | MentorKhoj")
            ->from($fromEmail, $fromName)
            ->replyTo($fromEmail, 'MentorKhoj Support')
            ->view('emails.prep-board-updated', [
                'board' => $this->board,
                'mentorName' => $mentorName,
                'studentName' => $this->board->mentee?->f_name ?: 'there',
                'link' => PrepBoardLogic::boardLink($this->board, false),
            ]);
    }
}
