<?php

namespace App\Mail;

use App\CentralLogics\PrepBoardLogic;
use App\Model\Mentor\PrepBoard;
use App\Model\Mentor\PrepBoardUpdate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PrepBoardDailyUpdateSubmitted extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public PrepBoard $board,
        public PrepBoardUpdate $update
    ) {}

    public function build(): self
    {
        $this->board->loadMissing(['mentor', 'mentee']);
        $this->update->loadMissing(['author']);
        $fromEmail = config('mail.from.address') ?: 'admin@mentorkhoj.com';
        $fromName = config('mail.from.name') ?: 'MentorKhoj';
        $studentName = $this->board->mentee
            ? (trim(($this->board->mentee->f_name ?? '') . ' ' . ($this->board->mentee->l_name ?? '')) ?: 'Student')
            : 'Student';
        $date = $this->update->entry_date?->format('d M Y') ?: '';

        return $this->subject("Daily update from {$studentName} · {$date} | MentorKhoj")
            ->from($fromEmail, $fromName)
            ->replyTo($fromEmail, 'MentorKhoj Support')
            ->view('emails.prep-board-daily-update-submitted', [
                'board' => $this->board,
                'update' => $this->update,
                'studentName' => $studentName,
                'entryDate' => $date,
                'body' => $this->update->body,
                'targetProgress' => is_array($this->update->target_progress) ? $this->update->target_progress : [],
                'link' => PrepBoardLogic::dailyUpdateLink($this->board, true),
            ]);
    }
}
