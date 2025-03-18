<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Comprobante extends Model
{
    protected $table = 'comprobantes';
    protected $primaryKey = 'idComprobante';
    
    protected $fillable = [
        'idTipoDocumento',
        'idPedido',
        'idUsuario',
        'serie',
        'correlativo',
        'fecha_emision',
        'sub_total',
        'mto_total',
    ];
}