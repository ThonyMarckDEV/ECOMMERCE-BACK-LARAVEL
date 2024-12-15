<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VerificarCorreo extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $url;

    public function __construct($user, $url)
    {
        $this->user = $user;
        $this->url = $url;
    }

    public function build()
    {
        return $this->subject('Verifica tu correo')
                    ->view('emails.verificar_correo')
                    ->with([
                        'user' => $this->user,
                        'url' => $this->url,
                    ]);
    }
}
