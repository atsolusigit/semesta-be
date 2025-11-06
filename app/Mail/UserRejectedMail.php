<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UserRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;

    public function __construct($userDataForEmail)
    {
        $this->user = $userDataForEmail;
    }

    public function build()
    {
        return $this->subject('Akun Anda Ditolak')
                    ->view('emails.user-rejected')
                    ->with([
                        'user' => $this->user
                    ]);
    }
}
