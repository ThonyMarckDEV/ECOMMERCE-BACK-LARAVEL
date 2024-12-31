<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DetalleComprobante extends Model
{
    use HasFactory;

    protected $table = 'detalle_comprobante';

    public $timestamps = false;

    protected $fillable = [
        'idComprobante',
        'descripcion',
        'cantidad',
        'precio_unitario',
        'total',
    ];

    public function comprobante()
    {
        return $this->belongsTo(Comprobante::class, 'idComprobante');
    }
}
