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
    public $pdfPath;
    public $ruc;  // Added RUC field

    public $idPedido;  // Added idPedido property

    public function __construct($nombreCompleto, $detallesPedido, $total, $pdfPath, $ruc, $idPedido)  // Added $ruc parameter
    {
        $this->nombreCompleto = $nombreCompleto;
        $this->detallesPedido = $detallesPedido;
        $this->total = $total;
        $this->pdfPath = $pdfPath;
        $this->ruc = $ruc;  // Assign RUC
        $this->idPedido = $idPedido;  // Store idPedido
    }

    public function build()
    {
        return $this->subject('Notificación de Pago Completado - Factura')
                    ->view('emails.notificacion_pago_completado_factura')
                    ->attach($this->pdfPath, [
                        'as' => 'factura.pdf',  // Changed from 'boleta.pdf' to 'factura.pdf'
                        'mime' => 'application/pdf',
                    ]);
    }
}