<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DetalleComprobante extends Model
{
    protected $table = 'detalle_comprobantes';
    protected $primaryKey = 'idDetalleComprobante';
    
    protected $fillable = [
        'idComprobante',
        'idProducto',
        'idTalla',
        'idModelo',
        'cantidad',
        'precio_unitario',
        'subtotal'
    ];

}