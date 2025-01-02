<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NotificacionPagoCompletadoFactura extends Mailable
{
    use Queueable, SerializesModels;

    public $nombreCompleto;
    public $detallesPedido;
    public $total;

    public function __construct($nombreCompleto, $detallesPedido, $total)
    {
        $this->nombreCompleto = $nombreCompleto;
        $this->detallesPedido = $detallesPedido;
        $this->total = $total;
    }

    public function build()
    {
        return $this->subject('Notificación de Pago Completado - Factura')
                    ->view('emails.notificacion_pago_completado_factura');
    }
}