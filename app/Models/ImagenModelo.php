<?php

// app/Models/ImagenModelo.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImagenModelo extends Model
{
    use HasFactory;

    protected $table = 'imagenes_modelo';
    protected $primaryKey = 'idImagen';

    public $timestamps = false;

    protected $fillable = [
        'urlImagen',
        'descripcion',
        'idModelo', // Clave foránea hacia modelos
    ];

    // Relación de muchos a uno hacia Modelo
    public function modelo()
    {
        return $this->belongsTo(Modelo::class, 'idModelo', 'idModelo');
    }
}
