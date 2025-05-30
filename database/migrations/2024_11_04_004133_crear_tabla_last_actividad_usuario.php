<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaLastActividadUsuario extends Migration
{
    public function up()
    {
        Schema::create('actividad_usuario', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('idUsuario');
            $table->timestamp('last_activity')->nullable();
            $table->text('dispositivo')->nullable(); // Agregar campo 'dispositivo'
            $table->text('jwt')->nullable(); // Agregar campo 'jwt'
            $table->boolean('session_active')->nullable(); // Agregar campo 'jwt'
            // Definir la clave foránea que hace referencia a `idUsuario` en la tabla `usuarios`
            $table->foreign('idUsuario')->references('idUsuario')->on('usuarios')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('actividad_usuario');
    }
}
