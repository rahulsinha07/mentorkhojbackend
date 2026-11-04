<?php

namespace App\Mail;

use App\CentralLogics\PrepBoardLogic;
use App\Model\Mentor\PrepBoard;
use App\Model\Mentor\PrepBoardTargetCycle;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PrepBoardTargetCycleReady extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public PrepBoard $board,
        public PrepBoardTargetCycle $cycle
    ) {}

    public function build(): self
    {
        $this->board->loadMissing(['mentee']);
        $fromEmail = config('mail.from.address') ?: 'admin@mentorkhoj.com';
        $fromName = config('mail.from.name') ?: 'MentorKhoj';
        $start = $this->cycle->start_date?->format('d M Y') ?: '';

        // PII-safe: no mentor name / phone in subject or body.
        return $this->subject('Your weekly study schedule is set | MentorKhoj')
            ->from($fromEmail, $fromName)
            ->replyTo($fromEmail, 'MentorKhoj Support')
            ->view('emails.prep-board-target-cycle-ready', [
                'board' => $this->board,
                'studentName' => $this->board->mentee?->f_name ?: 'there',
                'startLabel' => $start,
                'link' => PrepBoardLogic::dailyUpdateLink($this->board, false),
            ]);
    }
}
