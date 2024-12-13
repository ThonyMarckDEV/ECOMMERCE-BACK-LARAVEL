<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DetalleDireccion extends Model
{
    use HasFactory;

    protected $table = 'detalle_direcciones';

    protected $primaryKey = 'idDireccion';


    protected $fillable = [
        'idUsuario', 'departamento', 'provincia', 'distrito', 'direccion', 'estado', 'latitud', 'longitud'
    ];

    
    public $timestamps = false;

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'idUsuario');
    }
}