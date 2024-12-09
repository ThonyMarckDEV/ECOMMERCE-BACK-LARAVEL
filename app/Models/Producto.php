<?php

// app/Models/Producto.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    use HasFactory;

    protected $table = 'productos';
    protected $primaryKey = 'idProducto';

    public $timestamps = false;

    protected $fillable = [
        'nombreProducto',
        'descripcion',
        'precio',
        'stock',
        'idCategoria', // Clave foránea hacia la tabla categorias
    ];

    // Relación de muchos a uno hacia Categoria
    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'idCategoria', 'idCategoria');
    }

    public function detallesCarrito()
    {
        return $this->hasMany(CarritoDetalle::class, 'idProducto', 'idProducto');
    }

    public function pedidos()
    {
        return $this->hasMany(PedidoDetalle::class, 'idProducto', 'idProducto');
    }


    public function stocks()
    {
        return $this->hasManyThrough(
            Stock::class, 
            Modelo::class,
            'idProducto', // Clave foránea de Producto en Modelo
            'idModelo',   // Clave foránea de Modelo en Stock
            'idProducto', // Clave primaria de Producto
            'idModelo'    // Clave primaria de Modelo
        );
    }

}
