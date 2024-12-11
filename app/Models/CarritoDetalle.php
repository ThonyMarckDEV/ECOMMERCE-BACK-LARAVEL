<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CarritoDetalle extends Model
{
    use HasFactory;

    protected $table = 'carrito_detalle';
    
    protected $primaryKey = 'idDetalle'; // Define la clave primaria correcta

    public $timestamps = false;

    // Permitir asignación masiva para estos campos
    protected $fillable = ['idCarrito', 'idProducto', 'idModelo', 'idTalla', 'cantidad', 'subtotal'];
    
    public function carrito()
    {
        return $this->belongsTo(Carrito::class, 'idCarrito');
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'idProducto', 'idProducto');
    }

    // Relación con Modelo (si existe)
    public function modelo()
    {
        return $this->belongsTo(Modelo::class, 'idModelo', 'idModelo');  // Verifica que el segundo parámetro sea 'idModelo'
    }
    
    public function talla()
    {
        return $this->belongsTo(Talla::class, 'idTalla', 'idTalla');  // Asegúrate de que el segundo parámetro esté bien configurado
    }
}
