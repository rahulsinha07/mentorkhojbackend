<?php

namespace App\Mail\Customer;

use App\CentralLogics\FormMailLogic;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordReset extends Mailable
{
    use Queueable, SerializesModels;

    protected $token;
    protected $name;
    protected $link;
    protected $expiryMinutes;

    public function __construct($token, $name, $link = null, $expiryMinutes = 30)
    {
        $this->token = $token;
        $this->name = $name;
        $this->link = $link;
        $this->expiryMinutes = $expiryMinutes;
    }

    public function build()
    {
        $fromEmail = config('mail.from.address') ?: FormMailLogic::adminEmail();

        return $this->subject('Reset your MentorKhoj password')
            ->from($fromEmail, config('mail.from.name', 'MentorKhoj'))
            ->replyTo(FormMailLogic::adminEmail(), 'MentorKhoj Support')
            ->view('email-templates.customer.password-reset', [
                'token' => $this->token,
                'name' => $this->name,
                'link' => $this->link,
                'expiryMinutes' => $this->expiryMinutes,
            ]);
    }
}
