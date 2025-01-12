<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoPago extends Model
{
    use HasFactory;

    protected $primaryKey = 'idTipoPago';

    public $timestamps = false;

    protected $table = 'tipo_pago'; // Nombre de la tabla
    protected $fillable = ['nombre', 'status']; // Campos que se pueden asignar masivamente
}