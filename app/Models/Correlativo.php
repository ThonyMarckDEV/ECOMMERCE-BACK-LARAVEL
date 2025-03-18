<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Correlativo extends Model
{
    use HasFactory;

    protected $table = 'correlativos';

    protected $primaryKey = 'idCorrelativo'; // Llave primaria explícita

    protected $fillable = [
        'idTipoDocumento', // Relación con tipo_documentos
        'codigo',
        'numero_serie',      // Ej: F001, B001
        'numero_actual',     // Correlativo actual
    ];

    /**
     * Relación con el modelo TipoDocumento
     */
    public function tipoDocumento()
    {
        return $this->belongsTo(TipoDocumento::class, 'idTipoDocumento');
    }
}
