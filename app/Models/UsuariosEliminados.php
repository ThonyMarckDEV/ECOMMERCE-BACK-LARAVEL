<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UsuariosEliminados extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'usuarios_eliminados';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'idEliminado';

    public $timestamps = false;
    /**
     * Indicates if the model's ID is auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'idUsuario',
        'nombres',
        'apellidos',
        'dni',
        'correo',
        'fecha_creado',
        'fecha_eliminado',
        'estado',
        'descripcion',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'fecha_creado' => 'datetime',
        'fecha_eliminado' => 'datetime',
    ];
}