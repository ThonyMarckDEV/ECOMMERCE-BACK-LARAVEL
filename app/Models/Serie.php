<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Serie extends Model
{
    use HasFactory;

    protected $table = 'serie';

    protected $primaryKey = 'idSerie';

    public $timestamps = false;

    protected $fillable = [
        'digitos',
        'nro_serie',
        'descripcion',
        'ecommerce',
    ];

    // Relación de uno a muchos con Numeracion
    public function numeraciones()
    {
        return $this->hasMany(Numeracion::class, 'idSerie', 'idSerie');
    }
}
