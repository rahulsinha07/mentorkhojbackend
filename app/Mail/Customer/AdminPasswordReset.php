<?php

namespace App\Mail\Customer;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AdminPasswordReset extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        private string $name,
        private string $password,
    ) {}

    public function build()
    {
        return $this->subject('Your MentorKhoj password was reset')
            ->view('email-templates.customer.admin-password-reset', [
                'name' => $this->name,
                'password' => $this->password,
            ]);
    }
}
