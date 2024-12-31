<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoDocumento extends Model
{
    use HasFactory;

    protected $table = 'tipo_documentos';

    protected $primaryKey = 'idTipoDocumento'; // Llave primaria explícita

    protected $fillable = [
        'codigo',        // Ej: 01 (Factura), 03 (Boleta)
        'descripcion',   // Ej: Factura, Boleta
        'abreviatura',   // Ej: F (Factura), B (Boleta)
    ];

    // Relación con correlativos
    public function correlativos()
    {
        return $this->hasMany(Correlativo::class, 'idTipoDocumento');
    }
}
