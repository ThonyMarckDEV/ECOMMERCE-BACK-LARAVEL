<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CaracteristicaProducto extends Model
{
    use HasFactory;

    protected $table = 'caracteristicas_producto';
    protected $primaryKey = 'idCaracteristica';
    protected $fillable = ['idProducto', 'caracteristicas'];

    public $timestamps = false;

    // Relación con el producto
    public function producto()
    {
        return $this->belongsTo(Producto::class, 'idProducto', 'idProducto');
    }
}