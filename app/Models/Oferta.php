<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Oferta extends Model
{
    protected $table = 'ofertas';
    protected $primaryKey = 'idOferta'; // Define la clave primaria correcta

    public $timestamps = false;
    
    protected $fillable = [
        'descripcion',
        'porcentajeDescuento',
        'fechaInicio',
        'fechaFin',
        'estado'
    ];

    // Relación muchos a muchos con Producto a través de la tabla intermedia ProductosOfertas
    public function productos(): BelongsToMany
    {
        return $this->belongsToMany(Producto::class, 'productosofertas', 'idOferta', 'idProducto');
    }
}