<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActividadUsuario extends Model
{
    use HasFactory;

    protected $table = 'actividad_usuario'; // Nombre personalizado de la tabla
    protected $fillable = ['idUsuario', 'last_activity', 'dispositivo', 'jwt','refresh_token','session_active']; // Campos adicionales
    public $timestamps = false;

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'idUsuario'); // Relación con el modelo Usuario
    }
}
