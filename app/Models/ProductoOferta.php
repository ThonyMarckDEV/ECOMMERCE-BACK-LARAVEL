<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductoOferta extends Model
{
    protected $table = 'productosofertas';
    protected $primaryKey = 'idOfertaProducto'; // Define la clave primaria correcta

    public $timestamps = false;
    
    protected $fillable = [
        'idProducto',
        'idOferta'
    ];

    // Relación pertenece a Oferta
    public function oferta(): BelongsTo
    {
        return $this->belongsTo(Oferta::class, 'idOferta');
    }

    // Relación pertenece a Producto
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'idProducto');
    }
}