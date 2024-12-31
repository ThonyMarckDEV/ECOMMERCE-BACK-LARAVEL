<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comprobante extends Model
{
    use HasFactory;

    protected $table = 'comprobante';

    public $timestamps = false;

    protected $fillable = [
        'idPedido',
        'tipo_comprobante',
        'serie',
        'correlativo',
        'total',
        'estado',
        'fecha_emision',
    ];

    public function pedido()
    {
        return $this->belongsTo(Pedido::class, 'idPedido');
    }

    public function detalles()
    {
        return $this->hasMany(DetalleComprobante::class, 'idComprobante');
    }
}
