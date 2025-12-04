<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UserRegistrationConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;

    public function __construct($user)
    {
        $this->user = $user;
    }

    public function build()
    {
<<<<<<< HEAD
        return $this->subject('Registrasi Berhasil - Menunggu Persetujuan Admin')
=======
        return $this->subject('Registrasi ke Aplikasi Manajemen Risiko Berhasil - Menunggu Persetujuan Admin')
>>>>>>> c25d44c91562d73f06dbf7a5ec1f721825bdbfae
                    ->view('emails.user-registration-confirmation');
    }
}
