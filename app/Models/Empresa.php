<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Empresa extends Model
{
    use HasFactory;

    protected $table = 'empresa';
    
    protected $primaryKey = 'razon_social';

    public $timestamps = false;

    // Permitir asignación masiva para estos campos
    protected $fillable = ['ruc', 'igv'];

}
