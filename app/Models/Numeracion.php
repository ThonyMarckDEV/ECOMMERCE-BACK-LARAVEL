<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Numeracion extends Model
{
    use HasFactory;

    protected $table = 'numeracion';

    public $timestamps = false;

    protected $fillable = [
        'tipo_comprobante',
        'idSerie',
        'numero',
    ];

    // Relación de uno a uno con TipoComprobante
    public function TipoComprobante()
    {
        return $this->hasOne(TipoComprobante::class, 'codigo', 'tipo_comprobante');
    }

    // Relación de uno a uno con Serie
    public function Serie()
    {
        return $this->belongsTo(Serie::class, 'idSerie', 'idSerie');
    }
}
