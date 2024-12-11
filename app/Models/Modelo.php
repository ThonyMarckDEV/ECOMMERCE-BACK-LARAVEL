<?php

// app/Models/Modelo.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Modelo extends Model
{
    use HasFactory;

    protected $table = 'modelos';
    protected $primaryKey = 'idModelo';

    public $timestamps = false;

    protected $fillable = [
        'nombreModelo',
        'descripcion',
        'idProducto', // Clave foránea hacia productos
    ];

    // Relación de muchos a uno hacia Producto
    public function producto()
    {
        return $this->belongsTo(Producto::class, 'idProducto', 'idProducto');
    }

    // Relación de uno a muchos hacia ImagenModelo
    public function imagenes()
    {
        return $this->hasMany(ImagenModelo::class, 'idModelo', 'idModelo');
    }

   // Relación con el stock
   public function stock()
   {
       return $this->hasMany(Stock::class, 'idModelo', 'idModelo');
   }
}
