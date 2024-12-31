<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoComprobante extends Model
{
    use HasFactory;

    protected $table = 'tipo_comprobante';

    public $timestamps = false;

    protected $fillable = [
        'codigo',
        'tipo_documento',
        'abreviatura'
    ];

    // Relación de uno a muchos con Numeracion
    public function numeraciones()
    {
        return $this->hasMany(Numeracion::class, 'tipo_comprobante', 'codigo');
    }
}
