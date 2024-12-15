<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NotificacionPedidoCancelado extends Mailable
{
    use Queueable, SerializesModels;

    public $nombreCompleto;
    public $idPedido;

    public function __construct($nombreCompleto, $idPedido)
    {
        $this->nombreCompleto = $nombreCompleto;
        $this->idPedido = $idPedido;
    }

    public function build()
    {
        return $this->view('emails.notificacionPedidoCancelado')
                    ->subject('Su Pedido ha sido Cancelado');
    }
}
