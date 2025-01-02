<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NotificacionPagoCompletadoBoleta extends Mailable
{
    use Queueable, SerializesModels;

    public $nombreCompleto;
    public $detallesPedido;
    public $total;

    public $pdfPath;

    public function __construct($nombreCompleto, $detallesPedido, $total,$pdfPath)
    {
        $this->nombreCompleto = $nombreCompleto;
        $this->detallesPedido = $detallesPedido;
        $this->total = $total;
        $this->pdfPath = $pdfPath;
    }

    public function build()
    {
        return $this->subject('Notificación de Pago Completado - Boleta')
                    ->view('emails.notificacion_pago_completado_boleta')
                    ->attach($this->pdfPath, [
                        'as' => 'boleta.pdf',
                        'mime' => 'application/pdf',
                    ]);
    }
}